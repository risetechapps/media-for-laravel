<?php

namespace RiseTechApps\Media\Traits\HasConversionsMedia;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasConversionsMedia
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->pdfPageNumber(1)
            ->format('png')
            ->nonOptimized()
            ->queued();
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('profile')
            ->withResponsiveImages()
            ->singleFile();

        $this
            ->addMediaCollection('icon_system')
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('uploads')
            ->withResponsiveImages();
    }

    public function medias(): HasMany
    {
        return $this->hasMany(\RiseTechApps\Media\Models\Media::class, 'model_id', 'id');
    }

    public function mediaProfile(): HasOne
    {
        return $this->hasOne(\RiseTechApps\Media\Models\Media::class, 'model_id', 'id')
            ->where('collection_name', 'profile');
    }

    public function mediaIconSystem(): HasOne
    {
        return $this->hasOne(\RiseTechApps\Media\Models\Media::class, 'model_id', 'id')
            ->where('collection_name', 'icon_system');
    }

    public function mediaUploads(): HasOne
    {
        return $this->hasOne(\RiseTechApps\Media\Models\Media::class, 'model_id', 'id')
            ->where('collection_name', 'uploads');
    }
}
