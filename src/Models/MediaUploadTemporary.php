<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\HasUuid\Traits\HasUuid\HasUuid;
use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use Spatie\MediaLibrary\HasMedia;

class MediaUploadTemporary extends Model implements HasMedia
{
    use HasConversionsMedia, HasUuid;
    use Prunable;

    protected function pruning(): void
    {
        $media = $this->medias()->first();

        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $pathFolder = dirname($path);

        if (Storage::disk($disk)->exists($pathFolder)) {
            Storage::disk($disk)->deleteDirectory($pathFolder);
        }

        $media->forceDelete();
    }

    public function prunable(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
    {
        return static::where('created_at', '<=', now()->subDays(2));
    }
}
