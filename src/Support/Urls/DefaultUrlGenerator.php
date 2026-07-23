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
 *
 * Com `media.cdn.base` preenchido, a URL de exibição passa a apontar para o CDN
 * (URL pública, sem assinatura) em vez do disco — sem precisar trocar de gerador.
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
        // CDN na frente do bucket: URL pública, dispensa assinatura e cache.
        if ($base = $this->cdnBase()) {
            return $this->cdnUrl($base, $file);
        }

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
     * Base do CDN, ou null quando não configurado.
     */
    protected function cdnBase(): ?string
    {
        $base = rtrim((string) config('media.cdn.base', ''), '/');

        return $base !== '' ? $base : null;
    }

    /**
     * URL pública via CDN: base + chave do objeto.
     *
     * A chave é montada conforme para onde o CDN aponta (`media.cdn.include_disk_root`):
     * - true  → raiz do bucket: chave = root do disco + path da mídia.
     * - false → raiz do disco:  chave = só o path da mídia.
     */
    protected function cdnUrl(string $base, MediaFile $file): string
    {
        $path = ltrim($file->path, '/');

        if (config('media.cdn.include_disk_root', true)) {
            $root = trim((string) config("filesystems.disks.{$file->disk}.root", ''), '/');

            if ($root !== '') {
                $path = "{$root}/{$path}";
            }
        }

        return "{$base}/{$path}";
    }

    /**
     * Só discos que assinam URL (S3 e compatíveis) precisam do fluxo temporário.
     */
    protected function isSignedDisk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
