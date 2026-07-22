<?php

namespace RiseTechApps\Media\Listeners;

use RiseTechApps\Media\Events\MediaHasBeenAdded;
use RiseTechApps\Media\Jobs\GenerateResponsiveImagesJob;
use RiseTechApps\Media\Jobs\PerformConversionsJob;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Conversions\Conversion;
use RiseTechApps\Media\Support\Conversions\ConversionEngine;

/**
 * Dispara a geração de derivados assim que a mídia é anexada.
 *
 * Conversões marcadas como enfileiradas vão para a fila em um único job;
 * as demais rodam na hora.
 */
class GenerateConversions
{
    public function __construct(protected ConversionEngine $engine)
    {
    }

    public function handle(MediaHasBeenAdded $event): void
    {
        $media = $event->media;

        $this->dispatchResponsiveImages($media);

        $owner = $media->model;

        if (! $owner || ! method_exists($owner, 'getMediaConversions')) {
            return;
        }

        $conversions = array_filter(
            $owner->getMediaConversions($media),
            fn (Conversion $conversion) => $conversion->appliesToCollection($media->collection_name)
        );

        if ($conversions === []) {
            return;
        }

        $queued = array_filter($conversions, fn (Conversion $c) => $c->isQueued());
        $immediate = array_filter($conversions, fn (Conversion $c) => ! $c->isQueued());

        if ($immediate !== []) {
            $this->engine->perform($media, array_values($immediate));
        }

        if ($queued !== []) {
            $names = array_map(fn (Conversion $c) => $c->name, $queued);

            dispatch(new PerformConversionsJob($media, array_values($names)));
        }
    }

    /**
     * Enfileira as variantes responsivas quando a mídia as pediu
     * (responsive_images.pending) e o master switch da config está ligado.
     */
    protected function dispatchResponsiveImages(Media $media): void
    {
        if (! config('media.responsive_images.enabled', false)) {
            return;
        }

        if (! data_get($media->responsive_images, 'pending')) {
            return;
        }

        dispatch(new GenerateResponsiveImagesJob($media));
    }
}
