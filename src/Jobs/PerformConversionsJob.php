<?php

namespace RiseTechApps\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Conversions\ConversionEngine;

/**
 * Gera as conversões marcadas como enfileiradas.
 *
 * Recebe apenas os nomes: as definições são reconstruídas a partir do model
 * dono no momento da execução, evitando serializar objetos de configuração.
 */
class PerformConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    /**
     * Mídia apagada antes do job sair da fila (troca de arquivo em coleção de
     * arquivo único) descarta o job em vez de marcá-lo como falho: o derivado
     * perdeu o destino, não houve erro.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<int, string>  $conversionNames
     */
    public function __construct(
        protected Media $media,
        protected array $conversionNames,
    ) {
    }

    public function handle(ConversionEngine $engine): void
    {
        // Reconferido no banco: o job pode ter esperado na fila enquanto a mídia
        // era apagada em definitivo. Sem isso, o trabalho todo é feito para
        // esbarrar na foreign key de `media_files` no fim.
        if (! $this->media->stillExists()) {
            return;
        }

        $owner = $this->media->model;

        if (! $owner) {
            return;
        }

        $conversions = array_filter(
            $owner->getMediaConversions($this->media),
            fn ($conversion) => in_array($conversion->name, $this->conversionNames, true)
        );

        $engine->perform($this->media, array_values($conversions));
    }
}
