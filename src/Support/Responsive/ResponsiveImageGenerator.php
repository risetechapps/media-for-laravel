<?php

namespace RiseTechApps\Media\Support\Responsive;

use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

/**
 * Gera versões reduzidas da imagem original em várias larguras.
 *
 * Cada largura vira uma variante responsive:{width} gravada pelo MediaFilesystem
 * — portanto entra na contabilidade de bytes como qualquer outro arquivo. Só
 * reduz: largura alvo maior que a original é ignorada, nunca amplia.
 */
class ResponsiveImageGenerator
{
    public function __construct(protected MediaFilesystem $filesystem)
    {
    }

    public function generate(Media $media): void
    {
        if (! $this->isDownscalableImage($media)) {
            return;
        }

        $original = $media->originalFile();

        if (! $original) {
            return;
        }

        $temporaryDirectory = (new TemporaryDirectory())->create();

        try {
            $localPath = $this->copyOriginalLocally($media, $original->disk, $original->path, $temporaryDirectory->path());

            if ($localPath === null) {
                return;
            }

            $originalWidth = $this->widthOf($localPath);

            if ($originalWidth === null) {
                return;
            }

            $generated = [];
            $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION)) ?: 'jpg';

            foreach ($this->targetWidths($originalWidth) as $width) {
                $target = $temporaryDirectory->path() . "/responsive-{$width}.{$extension}";

                Image::load($localPath)->width($width)->optimize()->save($target);

                $this->filesystem->storeResponsiveImage($media, $width, $target);

                $generated[] = $width;
            }

            // Substitui o marcador 'pending' pelas larguras realmente geradas —
            // o srcset é montado a partir dos arquivos, este campo é só o resumo.
            $media->forceFill(['responsive_images' => ['widths' => $generated]])->saveQuietly();
        } catch (Throwable $exception) {
            // Falha aqui não invalida a mídia: o original continua íntegro e
            // servível, apenas sem as variantes responsivas.
            report($exception);
        } finally {
            $temporaryDirectory->delete();
        }
    }

    protected function copyOriginalLocally(Media $media, string $disk, string $path, string $directory): ?string
    {
        $localPath = $directory . '/' . $media->file_name;

        $stream = Storage::disk($disk)->readStream($path);

        if (! $stream) {
            return null;
        }

        try {
            file_put_contents($localPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $localPath;
    }

    /**
     * Larguras da config menores que a original, sem repetição, da maior para a
     * menor. Amplia nunca: servir imagem esticada é pior que não ter a variante.
     */
    protected function targetWidths(int $originalWidth): array
    {
        $widths = array_filter(
            (array) config('media.responsive_images.widths', []),
            fn ($width) => is_int($width) && $width > 0 && $width < $originalWidth
        );

        $widths = array_values(array_unique($widths));

        rsort($widths);

        return $widths;
    }

    /**
     * Só imagens rasterizáveis pelo driver: SVG, PDF, vídeo e afins não entram.
     * getimagesize devolve null quando não sabe ler — o que já é o filtro certo.
     */
    protected function isDownscalableImage(Media $media): bool
    {
        return is_string($media->mime_type) && str_starts_with($media->mime_type, 'image/');
    }

    protected function widthOf(string $path): ?int
    {
        $info = @getimagesize($path);

        return $info[0] ?? null;
    }
}
