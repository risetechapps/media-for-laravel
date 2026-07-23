<?php

namespace RiseTechApps\Media\Support\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\Scopes\MediaScope;

/**
 * Leitura da contabilidade de storage.
 *
 * Soma o cache `media.total_size` — que já inclui original, conversões e
 * variantes responsivas de cada mídia. É a interface que dá sentido ao
 * rewrite: sem ela, os bytes contabilizados ficariam presos no banco.
 *
 * Por padrão inclui mídia na lixeira (soft delete): enquanto não é podada, o
 * arquivo continua em disco ocupando (e custando) storage. Passe
 * $includeTrashed = false para contar só o que está ativo.
 */
class StorageReport
{
    /**
     * Total de bytes ocupados por toda a mídia.
     */
    public function total(bool $includeTrashed = true): int
    {
        return (int) $this->query($includeTrashed)->sum('total_size');
    }

    /**
     * Bytes por coleção: ['avatars' => 1234, 'uploads' => 5678, ...].
     *
     * @return array<string, int>
     */
    public function byCollection(bool $includeTrashed = true): array
    {
        return $this->sumGroupedBy('collection_name', $this->query($includeTrashed));
    }

    /**
     * Bytes por tipo de model dono: ['App\\Models\\User' => 1234, ...].
     *
     * @return array<string, int>
     */
    public function byModelType(bool $includeTrashed = true): array
    {
        return $this->sumGroupedBy('model_type', $this->query($includeTrashed));
    }

    /**
     * Bytes de um model específico, opcionalmente restrito a uma coleção.
     */
    public function forModel(Model $model, ?string $collectionName = null, bool $includeTrashed = true): int
    {
        $query = $this->scopeToModel($this->query($includeTrashed), $model);

        if ($collectionName !== null) {
            $query->where('collection_name', $collectionName);
        }

        return (int) $query->sum('total_size');
    }

    /**
     * Bytes de um model, quebrados por coleção.
     *
     * @return array<string, int>
     */
    public function forModelByCollection(Model $model, bool $includeTrashed = true): array
    {
        $query = $this->scopeToModel($this->query($includeTrashed), $model);

        return $this->sumGroupedBy('collection_name', $query);
    }

    /**
     * Bytes de todos os models de um tipo (ex.: todos os App\Models\User).
     */
    public function forModelType(string $modelType, bool $includeTrashed = true): int
    {
        return (int) $this->query($includeTrashed)
            ->where('model_type', $modelType)
            ->sum('total_size');
    }

    /**
     * Envolve uma contagem de bytes num objeto Size, para converter/formatar:
     *
     *   Media::storage()->size(Media::storage()->total())->gb();
     *   Media::storage()->size($bytes)->forHumans();
     */
    public function size(int $bytes): Size
    {
        return Size::of($bytes);
    }

    /**
     * Formata bytes para leitura humana, unidade automática: 1536 => "1.5 KB".
     */
    public function humanize(int $bytes, int $precision = 2): string
    {
        return Size::of($bytes)->forHumans($precision);
    }

    /**
     * Converte bytes para a unidade pedida (B, KB, MB, GB, TB, PB).
     */
    public function toUnit(int $bytes, string $unit, int $precision = 2): float
    {
        return Size::of($bytes)->toUnit($unit, $precision);
    }

    // ----------------------------------------------------------------- interno

    protected function query(bool $includeTrashed): Builder
    {
        // Contabilidade é global por natureza: ignora o particionamento por
        // contexto (tenancy). Para uso por escopo, veja Quota::usage().
        $query = Media::withoutGlobalScope(MediaScope::class);

        return $includeTrashed ? $query->withTrashed() : $query;
    }

    protected function scopeToModel(Builder $query, Model $model): Builder
    {
        return $query
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey());
    }

    /**
     * @return array<string, int>
     */
    protected function sumGroupedBy(string $column, Builder $query): array
    {
        return $query
            ->selectRaw("{$column} as grouped, SUM(total_size) as bytes")
            ->groupBy($column)
            ->pluck('bytes', 'grouped')
            ->map(fn ($bytes) => (int) $bytes)
            ->all();
    }
}
