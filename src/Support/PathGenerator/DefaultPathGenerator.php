<?php

namespace RiseTechApps\Media\Support\PathGenerator;

use RiseTechApps\Media\Contracts\PathGeneratorContract;
use RiseTechApps\Media\Models\Media;

/**
 * Layout padrão em disco:
 *
 *   {collection}/{media_uuid}/arquivo.jpg
 *   {collection}/{media_uuid}/conversions/thumb.png
 *   {collection}/{media_uuid}/responsive/arquivo_400.jpg
 *
 * Manter tudo sob um diretório por mídia permite remover o conjunto inteiro
 * com uma única operação de deleteDirectory.
 */
class DefaultPathGenerator implements PathGeneratorContract
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media) . 'responsive/';
    }

    protected function basePath(Media $media): string
    {
        return trim($media->collection_name, '/') . '/' . $media->getKey() . '/';
    }
}
