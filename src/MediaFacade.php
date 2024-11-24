<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RiseTechApps\Media\Skeleton\SkeletonClass
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
