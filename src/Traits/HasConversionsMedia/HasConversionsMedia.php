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

    public function uploads(): HasMany
    {
        return $this->hasMany(\RiseTechApps\Media\Models\Media::class, 'model_id', 'id')
            ->where('collection_name', 'uploads');
    }

    public function getUploads(): array
    {
        try{
            return $this->getMedia('uploads')->toArray();
        }catch (\Exception $exception){
            logglyError()->performedOn(self::class)
                ->withProperties(['model' => $this])
                ->exception($exception)->withTags(['action' => 'getUploads'])->log("Error loading uploads");
            return [];
        }
    }

    public function getIconSystem(): ?Media
    {
        try {
            $icon = $this->getMedia('icon_system')->first();

            if (is_null($icon)) {
                return null;
            }

            return $icon;
        } catch (\Exception $exception) {

            logglyError()->performedOn(self::class)
                ->withProperties(['model' => $this])
                ->exception($exception)->withTags(['action' => 'getIconSystem'])->log("Error loading system icon");
            return null;
        }
    }
    public function iconSystem(): HasOne
    {
        return $this->hasOne(Media::class, 'model_id')->where('collection_name', 'icon');
    }
}
