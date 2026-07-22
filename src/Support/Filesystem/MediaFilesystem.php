<?php

namespace RiseTechApps\Media\Support\Filesystem;

use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Contracts\PathGeneratorContract;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\MediaFile;
use Throwable;

/**
 * Único caminho pelo qual bytes entram ou saem do disco.
 *
 * Toda escrita grava o arquivo E registra a linha em `media_files` E atualiza
 * `media.total_size`. Toda remoção faz o inverso. É essa invariante que torna
 * a contagem de storage exata — inclusive das conversões e variantes
 * responsivas, que ficam de fora de `media.size`.
 *
 * Escrever direto no Storage, contornando este serviço, fura a contabilidade.
 */
class MediaFilesystem
{
    public function __construct(protected PathGeneratorContract $pathGenerator)
    {
    }

    // -------------------------------------------------------------- escrita

    /**
     * Grava o arquivo original da mídia.
     *
     * @param  string       $source      Caminho local, ou chave no disco de origem.
     * @param  string|null  $sourceDisk  Disco de origem; null indica caminho local.
     * @param  bool         $preserveOriginal  Mantém a origem. Por padrão a origem
     *                                         é removida (semântica de mover).
     */
    public function storeOriginal(
        Media $media,
        string $source,
        ?string $sourceDisk = null,
        bool $preserveOriginal = false
    ): MediaFile {
        $destination = $this->pathGenerator->getPath($media) . $media->file_name;

        $size = $this->write($media->disk, $destination, $source, $sourceDisk);

        $file = $this->register($media, MediaFile::VARIANT_ORIGINAL, $media->disk, $destination, $size);

        if (! $preserveOriginal) {
            $this->removeSource($source, $sourceDisk);
        }

        return $file;
    }

    /**
     * Grava um arquivo de conversão gerado localmente.
     */
    public function storeConversion(Media $media, string $conversionName, string $localPath): MediaFile
    {
        $disk = $media->conversions_disk ?: $media->disk;
        $destination = $this->pathGenerator->getPathForConversions($media) . basename($localPath);

        $size = $this->write($disk, $destination, $localPath);

        return $this->register(
            $media,
            MediaFile::variantForConversion($conversionName),
            $disk,
            $destination,
            $size
        );
    }

    /**
     * Grava uma variante responsiva gerada localmente.
     */
    public function storeResponsiveImage(Media $media, int $width, string $localPath): MediaFile
    {
        $disk = $media->conversions_disk ?: $media->disk;
        $destination = $this->pathGenerator->getPathForResponsiveImages($media) . basename($localPath);

        $size = $this->write($disk, $destination, $localPath);

        return $this->register(
            $media,
            MediaFile::variantForResponsive($width),
            $disk,
            $destination,
            $size
        );
    }

    // -------------------------------------------------------------- remoção

    public function deleteFile(MediaFile $file): void
    {
        $media = $file->media;

        $file->deleteFromDisk();
        $file->delete();

        $media?->recalculateTotalSize();
    }

    public function deleteVariant(Media $media, string $variant): void
    {
        if ($file = $media->fileForVariant($variant)) {
            $this->deleteFile($file);
        }
    }

    /**
     * Remove todos os arquivos da mídia — o diretório inteiro em uma operação —
     * e zera os registros correspondentes.
     */
    public function deleteAllFiles(Media $media): void
    {
        $directory = rtrim($this->pathGenerator->getPath($media), '/');

        foreach ($media->files()->pluck('disk')->unique() as $disk) {
            Storage::disk($disk)->deleteDirectory($directory);
        }

        $media->files()->delete();

        $media->forceFill(['total_size' => 0])->saveQuietly();
    }

    // -------------------------------------------------------------- interno

    /**
     * Copia o conteúdo para o disco de destino e devolve o tamanho gravado.
     *
     * Usa stream para não carregar o arquivo inteiro em memória — relevante em
     * vídeos e uploads grandes.
     */
    protected function write(string $disk, string $destination, string $source, ?string $sourceDisk = null): int
    {
        $stream = $sourceDisk === null
            ? fopen($source, 'rb')
            : Storage::disk($sourceDisk)->readStream($source);

        if ($stream === false || $stream === null) {
            throw new \RuntimeException("Não foi possível ler a origem [{$source}].");
        }

        try {
            Storage::disk($disk)->writeStream($destination, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $this->resolveSize($disk, $destination, $source, $sourceDisk);
    }

    /**
     * Prefere medir a origem local (custo zero) a fazer HEAD no destino remoto.
     */
    protected function resolveSize(string $disk, string $destination, string $source, ?string $sourceDisk): int
    {
        if ($sourceDisk === null && is_file($source)) {
            return (int) filesize($source);
        }

        return (int) Storage::disk($disk)->size($destination);
    }

    /**
     * Registra o arquivo físico. Se já existir a mesma variante, o arquivo
     * anterior é removido do disco antes — evita byte órfão fora da contagem.
     *
     * Em falha no registro, o arquivo recém-gravado é removido para não deixar
     * bytes no disco sem linha correspondente.
     */
    protected function register(Media $media, string $variant, string $disk, string $path, int $size): MediaFile
    {
        try {
            $existing = $media->fileForVariant($variant);

            if ($existing && $existing->path !== $path) {
                $existing->deleteFromDisk();
            }

            $file = $media->files()->updateOrCreate(
                ['variant' => $variant],
                ['disk' => $disk, 'path' => $path, 'size' => $size],
            );

            $media->recalculateTotalSize();

            return $file;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    protected function removeSource(string $source, ?string $sourceDisk): void
    {
        if ($sourceDisk !== null) {
            Storage::disk($sourceDisk)->delete($source);

            return;
        }

        if (is_file($source)) {
            @unlink($source);
        }
    }
}
