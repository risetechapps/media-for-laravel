<?php

namespace RiseTechApps\Media\Support\Conversions\Generators;

use RiseTechApps\Media\Contracts\ImageGeneratorContract;
use RiseTechApps\Media\Support\Conversions\Conversion;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;

/**
 * Rasteriza uma página do PDF para servir de base à miniatura.
 *
 * Depende da extensão Imagick com suporte a Ghostscript. Sem isso, o gerador
 * se declara incapaz e a cadeia recai no ícone genérico.
 */
class PdfGenerator implements ImageGeneratorContract
{
    public function canHandle(?string $mimeType, string $extension): bool
    {
        if ($mimeType !== 'application/pdf' && strtolower($extension) !== 'pdf') {
            return false;
        }

        return extension_loaded('imagick');
    }

    public function generate(string $sourcePath, string $workingDirectory, Conversion $conversion): ?string
    {
        $target = $workingDirectory . '/pdf-page.jpg';

        (new Pdf($sourcePath))
            ->selectPage($conversion->getPdfPageNumber())
            ->format(OutputFormat::Jpg)
            ->save($target);

        // O save() pode acrescentar sufixo de página ao nome; resolve o que
        // realmente foi escrito antes de devolver.
        if (file_exists($target)) {
            return $target;
        }

        $generated = glob($workingDirectory . '/pdf-page*');

        return $generated[0] ?? null;
    }

    public function fitInside(): bool
    {
        return false;
    }
}
