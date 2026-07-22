<?php

namespace RiseTechApps\Media\Support\Conversions;

use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Contracts\ImageGeneratorContract;
use RiseTechApps\Media\Events\ConversionHasBeenCompleted;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

/**
 * Gera os arquivos derivados de uma mídia.
 *
 * Fluxo: baixa o original para um diretório temporário local, pede ao gerador
 * adequado uma imagem base (o próprio arquivo, uma página do PDF, um quadro do
 * vídeo ou um ícone), aplica as transformações e entrega ao MediaFilesystem —
 * que grava e contabiliza os bytes.
 */
class ConversionEngine
{
    /** @var array<int, ImageGeneratorContract> */
    protected array $generators;

    public function __construct(protected MediaFilesystem $filesystem)
    {
        $this->generators = array_map(
            fn (string $class) => app($class),
            config('media.conversions.generators', [])
        );
    }

    /**
     * @param  array<int, Conversion>  $conversions
     */
    public function perform(Media $media, array $conversions): void
    {
        if ($conversions === []) {
            return;
        }

        $temporaryDirectory = (new TemporaryDirectory())->create();

        try {
            $originalPath = $this->copyOriginalLocally($media, $temporaryDirectory->path());

            if ($originalPath === null) {
                return;
            }

            foreach ($conversions as $conversion) {
                $this->performOne($media, $conversion, $originalPath, $temporaryDirectory->path());
            }
        } finally {
            // O diretório temporário some mesmo em caso de erro: conversão que
            // falha não pode deixar arquivo acumulando no servidor.
            $temporaryDirectory->delete();
        }
    }

    protected function performOne(Media $media, Conversion $conversion, string $originalPath, string $workingDirectory): void
    {
        try {
            $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));

            $generator = $this->generatorFor($media->mime_type, $extension);

            if (! $generator) {
                return;
            }

            $base = $generator->generate($originalPath, $workingDirectory, $conversion);

            if ($base === null) {
                return;
            }

            $target = $workingDirectory . '/' . $conversion->getFileName(
                pathinfo($media->file_name, PATHINFO_FILENAME),
                $extension
            );

            $this->manipulate($base, $target, $conversion, $generator->fitInside());

            $this->filesystem->storeConversion($media, $conversion->name, $target);

            event(new ConversionHasBeenCompleted($media->refresh(), $conversion));
        } catch (Throwable $exception) {
            // Uma conversão que falha não invalida a mídia nem as demais
            // conversões — o original continua íntegro e servível.
            report($exception);
        }
    }

    protected function manipulate(string $source, string $target, Conversion $conversion, bool $fitInside = false): void
    {
        $image = Image::load($source);

        // Orientação primeiro: redimensionar antes de endireitar sairia com as
        // dimensões trocadas numa foto deitada.
        $conversion->shouldAutoOrient() && $image->orientation();

        $width = $conversion->getWidth();
        $height = $conversion->getHeight();

        // Ícones e afins: cabem inteiros no destino (Contain), sem corte,
        // sobrepondo o fit da conversão. Preenche a folga com o fundo definido.
        if ($fitInside && $width && $height) {
            $image->fit(Fit::Contain, $width, $height, false, $conversion->getBackground());
        } elseif ($conversion->getFit() && ($width || $height)) {
            $image->fit($conversion->getFit(), $width, $height, false, $conversion->getBackground());
        } else {
            $width && $image->width($width);
            $height && $image->height($height);
        }

        $conversion->getSharpen() && $image->sharpen($conversion->getSharpen());
        $conversion->getQuality() && $image->quality($conversion->getQuality());

        $image->format($conversion->getFormat(pathinfo($source, PATHINFO_EXTENSION)));

        // Otimização por último: opera sobre o resultado já no formato final.
        $conversion->shouldOptimize() && $image->optimize();

        $image->save($target);
    }

    /**
     * Traz o original para disco local: as bibliotecas de manipulação operam
     * sobre arquivos, não sobre streams remotos.
     */
    protected function copyOriginalLocally(Media $media, string $directory): ?string
    {
        $file = $media->originalFile();

        if (! $file) {
            return null;
        }

        $localPath = $directory . '/' . $media->file_name;

        $stream = Storage::disk($file->disk)->readStream($file->path);

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

    protected function generatorFor(?string $mimeType, string $extension): ?ImageGeneratorContract
    {
        foreach ($this->generators as $generator) {
            if ($generator->canHandle($mimeType, $extension)) {
                return $generator;
            }
        }

        return null;
    }
}
