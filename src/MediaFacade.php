<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void handleUploads(\Illuminate\Database\Eloquent\Model $model, array $uploads)
 * @method static void handleProfile(\Illuminate\Database\Eloquent\Model $model)
 *
 * @see \RiseTechApps\Media\Media
 */
class MediaFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'media';
    }
}
