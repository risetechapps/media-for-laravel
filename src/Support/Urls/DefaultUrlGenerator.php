<?php

namespace RiseTechApps\Media\Support\Urls;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Contracts\UrlGeneratorContract;
use RiseTechApps\Media\Models\MediaFile;

/**
 * Gerador de URL padrão: serve direto do disco configurado.
 *
 * Em discos S3 a URL de exibição é assinada e reaproveitada por alguns minutos
 * — sem cache, uma listagem de N mídias geraria N assinaturas por requisição.
 */
class DefaultUrlGenerator implements UrlGeneratorContract
{
    public function getUrl(MediaFile $file): string
    {
        return Storage::disk($file->disk)->url($file->path);
    }

    public function getTemporaryUrl(MediaFile $file, DateTimeInterface $expiresAt): string
    {
        return Storage::disk($file->disk)->temporaryUrl($file->path, $expiresAt);
    }

    public function getFullUrl(MediaFile $file): string
    {
        if (! $this->isSignedDisk($file->disk)) {
            return url($this->getUrl($file));
        }

        $cacheMinutes = (int) config('media.url.signed_cache_minutes', 55);
        $ttlMinutes = (int) config('media.url.signed_ttl_minutes', 60);

        return Cache::remember(
            "media:url:{$file->media_id}:{$file->variant}",
            now()->addMinutes($cacheMinutes),
            fn () => $this->getTemporaryUrl($file, now()->addMinutes($ttlMinutes))
        );
    }

    /**
     * Só discos que assinam URL (S3 e compatíveis) precisam do fluxo temporário.
     */
    protected function isSignedDisk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
