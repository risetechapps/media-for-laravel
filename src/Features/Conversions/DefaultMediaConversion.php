<?php

namespace RiseTechApps\Media\Features\Conversions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGenerator;

class DefaultMediaConversion extends ImageGenerator
{
    public function convert(string $file, ?Conversion $conversion = null): ?string
    {
        try {
            $pathInfo = pathinfo($file);
            $dirName = $pathInfo['dirname'];
            $fileName = $pathInfo['filename'];
            $extension = $pathInfo['extension'];

            $dir = realpath(__DIR__ . '/../../../resources/conversions/default');
            $default = $dir . DIRECTORY_SEPARATOR . $this->baseFile($extension);
            $newFile = $dirName . '/' . $fileName . '.png';

            file_put_contents($newFile, File::get($default));

            return $newFile;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function requirementsAreInstalled(): bool
    {
        $dir = realpath(__DIR__ . '/../../../resources/conversions/default');
        return File::exists($dir);
    }

    public function supportedExtensions(): Collection
    {
        return collect([
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx',
            'txt', 'rtf', 'odt', 'ods', 'odp', 'mp3', 'wav', 'aac',
            'ogg', 'flac', 'm4a', 'amr', 'mp4', 'avi', 'mov', 'mkv',
            'wmv', 'flv', 'webm', '3gp', 'zip', 'rar', '7z', 'tar',
            'gz', 'psd', 'ai', 'eps', 'indd', 'json', 'xml', 'sql',
        ]);
    }

    public function defaultFile($extension): string
    {
        $default = collect([
            'pdf' => 'pdf.png', 'doc' => 'docx.png', 'docx' => 'docx.png', 'xls' => 'excel.png', 'xlsx' => 'excel.png',
            'csv' => 'excel.png', 'ppt' => 'ppt.png', 'pptx' => 'ppt.png', 'txt' => 'txt.png',
            'rtf' => 'txt.png', 'odt' => 'document.png', 'ods' => 'document.png', 'odp' => 'document.png',
            'mp3' => 'audio.png', 'wav' => 'audio.png', 'aac' => 'audio.png', 'ogg' => 'audio.png',
            'flac' => 'audio.png', 'm4a' => 'audio.png', 'amr' => 'audio.png',
            'mp4' => 'audio.png', 'avi' => 'video.png', 'mov' => 'video.png', 'mkv' => 'video.png',
            'wmv' => 'video.png', 'flv' => 'video.png', 'webm' => 'video.png', '3gp' => 'video.png',
            'zip' => 'compactada.png', 'rar' => 'compactada.png', '7z' => 'compactada.png', 'tar' => 'compactada.png',
            'gz' => 'compactada.png', 'psd' => 'document.png', 'ai' => 'document.png', 'eps' => 'document.png',
            'indd' => 'document.png', 'json' => 'document.png', 'xml' => 'document.png', 'sql' => 'document.png',
        ]);

        return $default->get($extension);
    }

    public function supportedMimeTypes(): Collection
    {
        return collect([
            'image/jpeg', 'image/jpeg', 'image/png', 'image/gif', 'application/vnd.oasis.opendocument.text',
            'image/tiff', 'image/tiff', 'image/bmp', 'image/svg+xml', 'application/vnd.oasis.opendocument.spreadsheet',
            'image/webp', 'image/x-icon', 'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'application/rtf', 'application/vnd.oasis.opendocument.presentation',
            'audio/mpeg', 'audio/wav', 'audio/aac', 'audio/ogg', 'audio/flac', 'audio/mp4', 'audio/amr', 'video/mp4',
            'video/x-msvideo', 'video/quicktime', 'video/x-matroska', 'video/x-ms-wmv', 'video/x-flv',
            'video/webm', 'video/3gpp', 'application/zip', 'application/vnd.rar', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip', 'image/vnd.adobe.photoshop', 'application/postscript',
            'application/postscript', 'application/x-indesign', 'text/csv', 'application/json',
            'application/xml', 'application/sql'
        ]);
    }

    private function baseFile(mixed $extension): string
    {
        if ($this->supportedExtensions()->contains($extension)) {
            return $this->defaultFile($extension);
        }

        return '';
    }
}
