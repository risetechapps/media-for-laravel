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
     * @param  array<int, string>  $conversionNames
     */
    public function __construct(
        protected Media $media,
        protected array $conversionNames,
    ) {
    }

    public function handle(ConversionEngine $engine): void
    {
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
