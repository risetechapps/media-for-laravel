<?php

namespace RiseTechApps\Media\Support\Conversions\Generators;

use RiseTechApps\Media\Contracts\ImageGeneratorContract;
use RiseTechApps\Media\Support\Conversions\Conversion;

/**
 * Último recurso da cadeia: entrega um ícone representando o tipo do arquivo.
 *
 * Garante que documentos, planilhas, áudios e compactados também tenham
 * miniatura, em vez de aparecerem quebrados na interface.
 */
class FileIconGenerator implements ImageGeneratorContract
{
    protected const ICONS = [
        'pdf' => 'pdf.png',
        'doc' => 'docx.png', 'docx' => 'docx.png', 'odt' => 'document.png', 'rtf' => 'txt.png',
        'xls' => 'excel.png', 'xlsx' => 'excel.png', 'csv' => 'excel.png', 'ods' => 'document.png',
        'ppt' => 'ppt.png', 'pptx' => 'ppt.png', 'odp' => 'document.png',
        'txt' => 'txt.png',
        'mp3' => 'audio.png', 'wav' => 'audio.png', 'aac' => 'audio.png', 'ogg' => 'audio.png',
        'flac' => 'audio.png', 'm4a' => 'audio.png', 'amr' => 'audio.png',
        'mp4' => 'video.png', 'avi' => 'video.png', 'mov' => 'video.png', 'mkv' => 'video.png',
        'wmv' => 'video.png', 'flv' => 'video.png', 'webm' => 'video.png', '3gp' => 'video.png',
        'zip' => 'compactada.png', 'rar' => 'compactada.png', '7z' => 'compactada.png',
        'tar' => 'compactada.png', 'gz' => 'compactada.png',
        'svg' => 'image.png', 'ico' => 'image.png',
        'json' => 'code.png', 'xml' => 'code.png', 'html' => 'code.png', 'htm' => 'code.png',
        'css' => 'code.png', 'js' => 'code.png', 'ts' => 'code.png', 'php' => 'code.png',
        'yml' => 'code.png', 'yaml' => 'code.png', 'md' => 'code.png', 'sql' => 'code.png',
        'sh' => 'code.png', 'ini' => 'code.png', 'env' => 'code.png', 'log' => 'code.png',
    ];

    protected const FALLBACK = 'document.png';

    /**
     * Aceita qualquer coisa — é o fim da cadeia, precisa sempre responder.
     */
    public function canHandle(?string $mimeType, string $extension): bool
    {
        return true;
    }

    public function fitInside(): bool
    {
        return true;
    }

    public function generate(string $sourcePath, string $workingDirectory, Conversion $conversion): ?string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        $icon = $this->iconDirectory() . '/' . (self::ICONS[$extension] ?? self::FALLBACK);

        if (! file_exists($icon)) {
            return null;
        }

        // Copia para o diretório de trabalho: o passo seguinte manipula a
        // imagem, e o arquivo de resources não pode ser alterado.
        $target = $workingDirectory . '/icon.png';

        return copy($icon, $target) ? $target : null;
    }

    protected function iconDirectory(): string
    {
        return realpath(__DIR__ . '/../../../../resources/conversions/default')
            ?: __DIR__ . '/../../../../resources/conversions/default';
    }
}
