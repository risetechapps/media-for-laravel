<?php

namespace RiseTechApps\Media\Support\Conversions\Generators;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use RiseTechApps\Media\Contracts\ImageGeneratorContract;
use RiseTechApps\Media\Support\Conversions\Conversion;

/**
 * Extrai um quadro do vídeo para servir de base à miniatura.
 *
 * Exige os binários ffmpeg/ffprobe disponíveis no sistema. Ausentes, o gerador
 * se declara incapaz e a cadeia recai no ícone genérico.
 */
class VideoGenerator implements ImageGeneratorContract
{
    public function canHandle(?string $mimeType, string $extension): bool
    {
        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'video/')) {
            return false;
        }

        return $this->binariesAvailable();
    }

    public function generate(string $sourcePath, string $workingDirectory, Conversion $conversion): ?string
    {
        $target = $workingDirectory . '/video-frame.jpg';

        $ffmpeg = FFMpeg::create(config('media.conversions.ffmpeg', []));

        $ffmpeg->open($sourcePath)
            ->frame(TimeCode::fromSeconds(config('media.conversions.video_frame_second', 1)))
            ->save($target);

        return file_exists($target) ? $target : null;
    }

    public function fitInside(): bool
    {
        return false;
    }

    protected function binariesAvailable(): bool
    {
        $config = config('media.conversions.ffmpeg', []);

        $ffmpeg = $config['ffmpeg.binaries'] ?? 'ffmpeg';
        $ffprobe = $config['ffprobe.binaries'] ?? 'ffprobe';

        return $this->exists($ffmpeg) && $this->exists($ffprobe);
    }

    protected function exists(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary);
        }

        return (bool) shell_exec("command -v {$binary}");
    }
}
