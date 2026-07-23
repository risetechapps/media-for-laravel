<?php

namespace RiseTechApps\Media\Traits\HasMediaSuite;

use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Traits\InteractsWithMedia\InteractsWithMedia;
use Spatie\Image\Enums\Fit;

/**
 * Atalho para o caso comum: uma coleção padrão e uma conversão `thumb`, sem
 * repetir o boilerplate em cada model.
 *
 *   class Client extends Model implements MediaContract
 *   {
 *       use HasMediaSuite;
 *   }
 *
 * Não prende o consumidor aos defaults — há três formas de estender:
 *
 * 1. Adicionar sem perder os defaults, via os hooks opcionais:
 *
 *        protected function additionalMediaCollections(): void
 *        {
 *            $this->addMediaCollection('documentos')->singleFile();
 *        }
 *
 *        protected function additionalMediaConversions(?Media $media = null): void
 *        {
 *            $this->addMediaConversion('preview')->width(1024)->queued();
 *        }
 *
 * 2. Ajustar um default pontual, sobrescrevendo o método correspondente:
 *
 *        protected function defaultConversionFormat(): string { return 'png'; }
 *
 * 3. Trocar tudo, sobrescrevendo registerMediaCollections()/registerMediaConversions()
 *    (a trait deixa de mandar — é o comportamento normal da InteractsWithMedia).
 *
 * Os defaults saem da config `media.defaults` (ajuste global) e caem nos
 * fallbacks abaixo quando ausentes.
 */
trait HasMediaSuite
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $collection = $this->addMediaCollection($this->defaultMediaCollectionName());

        if ($this->defaultResponsiveImages()) {
            $collection->withResponsiveImages();
        }

        if (method_exists($this, 'additionalMediaCollections')) {
            $this->additionalMediaCollections();
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $conversion = $this->addMediaConversion($this->defaultConversionName())
            ->fit(Fit::Crop, $this->defaultConversionWidth(), $this->defaultConversionHeight())
            ->format($this->defaultConversionFormat())
            ->quality($this->defaultConversionQuality())
            ->orientation()
            ->optimize();

        // Enfileirada por padrão (produção com worker); nonQueued roda no request.
        $this->defaultConversionQueued() ? $conversion->queued() : $conversion->nonQueued();

        if (method_exists($this, 'additionalMediaConversions')) {
            $this->additionalMediaConversions($media);
        }
    }

    // --------------------------------------------------- defaults sobrescrevíveis

    protected function defaultMediaCollectionName(): string
    {
        return (string) config('media.defaults.collection', 'uploads');
    }

    protected function defaultResponsiveImages(): bool
    {
        return (bool) config('media.defaults.responsive_images', false);
    }

    protected function defaultConversionName(): string
    {
        return (string) config('media.defaults.conversion.name', 'thumb');
    }

    protected function defaultConversionWidth(): int
    {
        return (int) config('media.defaults.conversion.width', 368);
    }

    protected function defaultConversionHeight(): int
    {
        return (int) config('media.defaults.conversion.height', 232);
    }

    protected function defaultConversionFormat(): string
    {
        return (string) config('media.defaults.conversion.format', 'webp');
    }

    protected function defaultConversionQuality(): int
    {
        return (int) config('media.defaults.conversion.quality', 80);
    }

    protected function defaultConversionQueued(): bool
    {
        return (bool) config('media.defaults.conversion.queued', true);
    }
}
