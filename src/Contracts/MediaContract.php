<?php

namespace RiseTechApps\Media\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\File\FileAdder;
use RiseTechApps\Media\Support\File\RemoteFile;

/**
 * Implementado pelos models que possuem mídia.
 *
 * A implementação vem da trait InteractsWithMedia.
 */
interface MediaContract
{
    public function media(): MorphMany;

    public function addMedia(string|UploadedFile|RemoteFile $file): FileAdder;

    public function addMediaFromRequest(string $key): FileAdder;

    public function addMediaFromDisk(string $key, ?string $disk = null): FileAdder;

    public function addMediaFromUrl(string $url, ?string $fileName = null): FileAdder;

    public function getMedia(string $collectionName = 'default'): Collection;

    public function getFirstMedia(string $collectionName = 'default'): ?Media;

    public function hasMedia(string $collectionName = ''): bool;

    public function clearMediaCollection(string $collectionName = 'default'): static;

    public function clearMediaCollectionExcept(string $collectionName = 'default', array|Collection $excludedMedia = []): static;

    public function deleteAllMedia(): static;

    public function shouldDeletePreservingMedia(): bool;
}
