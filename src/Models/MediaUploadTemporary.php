<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\HasUuid\Traits\HasUuid;
use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use RiseTechApps\Monitoring\Traits\HasLoggly\HasLoggly;
use Spatie\MediaLibrary\HasMedia;

class MediaUploadTemporary extends Model implements HasMedia
{
    use HasConversionsMedia, HasUuid;
    use Prunable, HasLoggly;

    protected function pruning(): void
    {
        $media = $this->medias()->first();

        if (!$media) {
            return;
        }

        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $pathFolder = dirname($path);

        if (Storage::disk($disk)->exists($pathFolder)) {
            Storage::disk($disk)->deleteDirectory($pathFolder);
        }

        $media->forceDelete();
    }

    public function prunable(): Builder|MediaUploadTemporary
    {
        $days = config('media.expiration.temporary_uploads', 2);
        return static::where('created_at', '<=', now()->subDays($days));
    }
}
