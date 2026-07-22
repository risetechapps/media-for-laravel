<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Representa um arquivo físico em disco pertencente a uma mídia.
 *
 * Toda escrita passa pelo serviço de Filesystem, que cria a linha correspondente
 * aqui. Somar `size` desta tabela devolve o storage real ocupado — incluindo
 * conversões e variantes responsivas, que a coluna `media.size` não cobre.
 */
class MediaFile extends Model
{
    use HasUuids;

    public const VARIANT_ORIGINAL = 'original';

    protected $fillable = [
        'media_id',
        'variant',
        'disk',
        'path',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public static function variantForConversion(string $conversionName): string
    {
        return "conversion:{$conversionName}";
    }

    public static function variantForResponsive(int $width): string
    {
        return "responsive:{$width}";
    }

    public function isOriginal(): bool
    {
        return $this->variant === self::VARIANT_ORIGINAL;
    }

    /**
     * Remove o arquivo do disco. O registro é apagado por quem chama —
     * manter as duas operações juntas é responsabilidade do Filesystem.
     */
    public function deleteFromDisk(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }
}
