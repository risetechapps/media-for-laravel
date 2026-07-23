<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void syncUploads(\Illuminate\Database\Eloquent\Model $model, array $uploads, string $collectionName = 'uploads')
 * @method static void syncUploadsNow(\Illuminate\Database\Eloquent\Model $model, array $uploads, string $collectionName = 'uploads')
 * @method static \RiseTechApps\Media\Support\Reports\StorageReport storage()
 * @method static \RiseTechApps\Media\Support\Quota\Quota quota()
 * @method static void resolveScopeUsing(callable $resolver)
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
