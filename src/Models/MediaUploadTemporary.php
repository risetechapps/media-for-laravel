<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use RiseTechApps\HasUuid\Traits\HasUuid;
use RiseTechApps\Media\Contracts\MediaContract;
use RiseTechApps\Media\Traits\InteractsWithMedia\InteractsWithMedia;

/**
 * Dono provisório de um arquivo recém-enviado.
 *
 * Existe apenas entre o upload e o momento em que a mídia é vinculada ao model
 * definitivo. Não registra conversões: gerar derivados de algo descartável é
 * trabalho e storage jogados fora — as conversões acontecem no destino final.
 */
class MediaUploadTemporary extends Model implements MediaContract
{
    use HasUuid, InteractsWithMedia, Prunable;

    protected $table = 'media_upload_temporaries';

    protected $guarded = [];

    /**
     * Diferente dos models de domínio, o temporário remove a mídia em definitivo.
     * Mandar para a lixeira um arquivo que existe só durante o upload manteria
     * bytes pagos no storage sem nenhum motivo.
     */
    #[\Override]
    public function deleteAllMedia(): static
    {
        $this->media()
            ->cursor()
            ->each(fn (Media $media) => $media->forceDelete());

        return $this;
    }

    /**
     * Uploads abandonados (o usuário desistiu do formulário) expiram e são
     * removidos junto com seus arquivos.
     */
    public function prunable(): Builder
    {
        $days = config('media.expiration.temporary_uploads', 2);

        return static::query()->where('created_at', '<=', now()->subDays($days));
    }

    protected function pruning(): void
    {
        $this->deleteAllMedia();
    }
}
