<?php

namespace RiseTechApps\Media\Features\PathGenerator;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use \Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator as PathGenerator;

class DefaultPathGenerator extends PathGenerator
{
    protected function getBasePath(Media $media): string
    {

        try {

            $collection_name = $media->collection_name;

            return $collection_name . '/' . $media->uuid;
        } catch (\Exception $exception) {

        }

        return $media->uuid;
    }
}
