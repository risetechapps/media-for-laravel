<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\HasUuid\Traits\HasUuid;
use RiseTechApps\Monitoring\Traits\HasLoggly\HasLoggly;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaUploadTemporary extends Model implements HasMedia
{
    use InteractsWithMedia, HasUuid;
    use Prunable, HasLoggly;

    /**
     * Holder temporário: apenas segura o arquivo até ser movido para o model final.
     * Sem conversões/coleções registradas — evita gerar thumb/responsive descartáveis.
     * As conversões acontecem no model de destino, ao mover a mídia.
     */
    public function medias(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id', 'id');
    }

    protected function pruning(): void
    {
        $media = $this->medias()->first();

        if (!$media) {
            return;
        }

        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $pathFolder = dirname((string) $path);

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
