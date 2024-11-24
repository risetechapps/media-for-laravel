<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use RiseTechApps\Media\Features\PathGenerator\DefaultPathGenerator;
use RiseTechApps\Media\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Config::set('media-library.disk_name', config('filesystems.default'));
        Config::set('media-library.media_model', \RiseTechApps\Media\Models\Media::class);
        Config::set('media-library.prefix', 'uploads');
        $image_generators = config('media-library.image_generators');
        Config::set('media-library.image_generators', $image_generators);
        Config::set('media-library.path_generator', DefaultPathGenerator::class);
        Media::observe(new MediaObserver);
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Register the main class to use with the facade
        $this->app->singleton('media', function () {
            return new Media();
        });
    }
}
