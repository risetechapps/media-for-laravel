<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('media.php'),
            ], 'config');
        }

        Config::set('media-library.disk_name', config('filesystems.default'));
        Config::set('media-library.media_model', \RiseTechApps\Media\Models\Media::class);
        Config::set('media-library.prefix', 'uploads');
        $image_generators = config('media-library.image_generators');
        Config::set('media-library.image_generators', $image_generators);
        Config::set('media-library.path_generator', DefaultPathGenerator::class);
        Media::observe(new MediaObserver);


        $this->setPrefixFilesystems();
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

        if (file_exists(base_path('config/media.php'))) {
            $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'media');
        }
    }

    public function setPrefixFilesystems(): void
    {
        $disks = $this->app['config']['filesystems.disks'];

        $exclude = $this->app['config']['media.disk.exclude'];

        $prefix = $this->app['config']['media.disk.prefix'];

        foreach ($disks as $key => $value) {

            if(in_array($key, $exclude)){
                continue;
            }

            Storage::forgetDisk($key);
        }

        foreach ($disks as $disk => $value) {

            if(in_array($disk, $exclude)){
                continue;
            }

            $originalRoot = $this->app['config']["filesystems.disks.{$disk}"];
            $this->pathsOriginal['disks'][$disk] = $originalRoot;

            $pathRoot = $originalRoot['root'] ?? '';

            $bar = empty($pathRoot) ? '' : '/';

            $this->app['config']["filesystems.disks.{$disk}.root"] = $pathRoot .  $bar  . "${prefix}";
        }
    }
}
