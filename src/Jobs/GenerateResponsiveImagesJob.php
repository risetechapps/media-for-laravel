<?php

namespace RiseTechApps\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Responsive\ResponsiveImageGenerator;

/**
 * Gera as variantes responsivas da mídia fora do ciclo da requisição.
 *
 * Reduzir a imagem em várias larguras é trabalho de CPU/IO que não deve segurar
 * a resposta do upload — daí ir para a fila, como as conversões enfileiradas.
 */
class GenerateResponsiveImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(protected Media $media)
    {
    }

    public function handle(ResponsiveImageGenerator $generator): void
    {
        $generator->generate($this->media);
    }
}
