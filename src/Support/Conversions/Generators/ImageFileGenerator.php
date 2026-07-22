<?php

namespace RiseTechApps\Media\Support\Conversions\Generators;

use Imagick;
use RiseTechApps\Media\Contracts\ImageGeneratorContract;
use RiseTechApps\Media\Support\Conversions\Conversion;

/**
 * Arquivos que já são imagem: o próprio original serve de base.
 *
 * HEIC/HEIF (foto de iPhone) é exceção: o driver padrão do Spatie é GD, que
 * não lê o formato. Rasteriza para JPG via Imagick antes de devolver.
 */
class ImageFileGenerator implements ImageGeneratorContract
{
    protected const SUPPORTED = [
        'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif',
        'image/webp', 'image/bmp', 'image/tiff', 'image/avif',
    ];

    protected const HEIC_MIMES = ['image/heic', 'image/heif'];

    protected const HEIC_EXTENSIONS = ['heic', 'heif'];

    public function canHandle(?string $mimeType, string $extension): bool
    {
        if (in_array($mimeType, self::SUPPORTED, true)) {
            return true;
        }

        // HEIC/HEIF: o mime às vezes chega como octet-stream, então checa também
        // a extensão. Só assume se o Imagick tiver libheif; senão passa a vez e
        // cai no ícone, em vez de estourar a conversão.
        if ($this->isHeic($mimeType, $extension)) {
            return $this->heicSupported();
        }

        return false;
    }

    public function generate(string $sourcePath, string $workingDirectory, Conversion $conversion): ?string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (in_array($extension, self::HEIC_EXTENSIONS, true)) {
            return $this->rasterizeHeic($sourcePath, $workingDirectory);
        }

        return $sourcePath;
    }

    public function fitInside(): bool
    {
        return false;
    }

    protected function isHeic(?string $mimeType, string $extension): bool
    {
        return in_array($mimeType, self::HEIC_MIMES, true)
            || in_array(strtolower($extension), self::HEIC_EXTENSIONS, true);
    }

    protected function heicSupported(): bool
    {
        return extension_loaded('imagick') && Imagick::queryFormats('HEIC') !== [];
    }

    protected function rasterizeHeic(string $sourcePath, string $workingDirectory): ?string
    {
        $target = $workingDirectory . '/heic-source.jpg';

        $imagick = new Imagick($sourcePath);
        $imagick->setImageFormat('jpeg');
        $imagick->writeImage($target);
        $imagick->clear();

        return file_exists($target) ? $target : null;
    }
}
