<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RiseTechApps\Media\Contracts\UrlGeneratorContract;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;

class Media extends Model
{
    use HasUuids, SoftDeletes, Prunable;

    protected $table = 'media';

    protected $guarded = [];

    protected $attributes = [
        'manipulations' => '{}',
        'custom_properties' => '{}',
        'responsive_images' => '{}',
        'size' => 0,
        'total_size' => 0,
    ];

    protected function casts(): array
    {
        return [
            'manipulations' => 'array',
            'custom_properties' => 'array',
            'responsive_images' => 'array',
            'size' => 'integer',
            'total_size' => 'integer',
            'order_column' => 'integer',
        ];
    }

    /**
     * Soft delete mantém os arquivos em disco — eles continuam ocupando (e
     * custando) storage, e seguem visíveis na contagem via `deleted_at`.
     * Só a exclusão definitiva remove os bytes.
     *
     * A limpeza acontece em `deleting`, não em `forceDeleted`: depois da
     * exclusão as linhas de `media_files` já teriam sido removidas em cascata,
     * e não haveria como saber quais arquivos apagar.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $media) {
            if ($media->isForceDeleting()) {
                app(MediaFilesystem::class)->deleteAllFiles($media);
            }
        });
    }

    // ---------------------------------------------------------------- relações

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Arquivos físicos desta mídia: original, conversões e variantes responsivas.
     */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    // ------------------------------------------------------------ contabilidade

    /**
     * Recalcula `total_size` a partir dos arquivos físicos registrados.
     *
     * Fonte da verdade é `media_files`; `total_size` é apenas o cache dessa soma
     * para evitar agregação em toda leitura.
     */
    public function recalculateTotalSize(): int
    {
        $total = (int) $this->files()->sum('size');

        $this->forceFill(['total_size' => $total])->saveQuietly();

        return $total;
    }

    public function fileForVariant(string $variant): ?MediaFile
    {
        return $this->files()->firstWhere('variant', $variant);
    }

    public function originalFile(): ?MediaFile
    {
        return $this->fileForVariant(MediaFile::VARIANT_ORIGINAL);
    }

    /**
     * Uma conversão só é considerada gerada se existe arquivo físico para ela.
     */
    public function hasGeneratedConversion(string $conversionName): bool
    {
        return $this->files()
            ->where('variant', MediaFile::variantForConversion($conversionName))
            ->exists();
    }

    public function generatedConversions(): array
    {
        return $this->files()
            ->where('variant', 'like', 'conversion:%')
            ->pluck('variant')
            ->map(fn (string $variant) => substr($variant, strlen('conversion:')))
            ->all();
    }

    // --------------------------------------------------------------------- urls

    /**
     * Resolve o arquivo de uma conversão; sem nome, devolve o original.
     * Se a conversão ainda não foi gerada, recai no original.
     */
    public function fileFor(?string $conversionName = null): ?MediaFile
    {
        if (blank($conversionName)) {
            return $this->originalFile();
        }

        return $this->fileForVariant(MediaFile::variantForConversion($conversionName))
            ?? $this->originalFile();
    }

    public function getPathRelativeToRoot(?string $conversionName = null): ?string
    {
        return $this->fileFor($conversionName)?->path;
    }

    public function getUrl(?string $conversionName = null): string
    {
        return $this->urlGenerator()->getUrl($this->requireFile($conversionName));
    }

    public function getTemporaryUrl(DateTimeInterface $expiresAt, ?string $conversionName = null): string
    {
        return $this->urlGenerator()->getTemporaryUrl($this->requireFile($conversionName), $expiresAt);
    }

    /**
     * URL pronta para exibição — delega ao gerador configurado
     * (config('media.url_generator')), trocável para CDN e afins.
     */
    public function getFullUrl(?string $conversionName = null): string
    {
        return $this->urlGenerator()->getFullUrl($this->requireFile($conversionName));
    }

    protected function urlGenerator(): UrlGeneratorContract
    {
        return app(UrlGeneratorContract::class);
    }

    /**
     * Há variantes responsivas geradas para esta mídia?
     */
    public function hasResponsiveImages(): bool
    {
        return $this->files()->where('variant', 'like', 'responsive:%')->exists();
    }

    /**
     * Monta o atributo srcset a partir das variantes responsivas geradas:
     *
     *   https://.../responsive/arquivo-1024.jpg 1024w, https://.../-480.jpg 480w
     *
     * Vazio quando não há variantes. Use junto de um `sizes` no <img>.
     */
    public function getSrcset(): string
    {
        return $this->responsiveImages()
            ->map(fn (array $item) => "{$item['url']} {$item['width']}w")
            ->implode(', ');
    }

    /**
     * Mesmas variantes do srcset, em forma estruturada — para APIs/JSON ou para
     * montar <picture> na mão.
     *
     * @return array<int, array{width: int, url: string}>
     */
    public function getSrcsetArray(): array
    {
        return $this->responsiveImages()->values()->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{width: int, url: string}>
     */
    protected function responsiveImages(): \Illuminate\Support\Collection
    {
        return $this->files()
            ->where('variant', 'like', 'responsive:%')
            ->get()
            ->map(fn (MediaFile $file) => [
                'width' => $this->widthFromVariant($file->variant),
                'url' => $this->urlGenerator()->getFullUrl($file),
            ])
            ->sortByDesc('width');
    }

    protected function widthFromVariant(string $variant): int
    {
        return (int) substr($variant, strlen('responsive:'));
    }

    protected function requireFile(?string $conversionName): MediaFile
    {
        return $this->fileFor($conversionName)
            ?? throw new \RuntimeException("A mídia [{$this->getKey()}] não possui arquivo em disco.");
    }

    // ------------------------------------------------------------------- scopes

    public function scopeInCollection(Builder $query, string $collectionName): Builder
    {
        return $query->where('collection_name', $collectionName);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column');
    }

    // -------------------------------------------------------------------- prune

    /**
     * Remove definitivamente as mídias em lixeira há mais tempo que o configurado.
     * A limpeza dos arquivos em disco é responsabilidade do serviço de Filesystem,
     * acionado na exclusão definitiva.
     */
    public function prunable(): Builder
    {
        $days = config('media.expiration.soft_deleted', 180);

        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays($days));
    }
}
