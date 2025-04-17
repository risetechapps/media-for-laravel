<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as MediaLibrary;

class Media extends MediaLibrary
{
    use SoftDeletes, Prunable;

    protected $fillable = ['order_column'];

    protected $hidden = [
        'model_type',
        'model_id',
        'uuid',
        'collection_name',
        'disk',
        'conversions_disk',
        'manipulations',
        'custom_properties',
        'generated_conversions',
        'order_column',
        'responsive_images',
        'custom_properties',
        'created_at',
        'updated_at',
        'original_url',
        'deleted_at',
    ];

    protected $appends = ['preview', 'thumb'];

    public function usingTemporaryUploads(): Media
    {
        $this->setAttribute('model_type', $this->model_type);
        $this->setAttribute('model_id', $this->model_id);
        $this->save();

        return $this;
    }

    public function getFullUrl(string $conversionName = ''): string
    {
        $disk = $this->disk;

        if (config("filesystems.disks.${disk}.driver") === 's3') {
            return $this->getTemporaryUrl(Carbon::now()->addHour(), $conversionName);
        }
        return url($this->getUrl($conversionName));
    }

    public function getPreviewAttribute(): string
    {
        return $this->getFullUrl();
    }

    public function getThumbAttribute(): string
    {
        return $this->getFullUrl('thumb');
    }

    protected function pruning(): void
    {
        $disk = $this->disk;
        $path = $this->getPathRelativeToRoot();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function prunable(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(90));
    }
}
