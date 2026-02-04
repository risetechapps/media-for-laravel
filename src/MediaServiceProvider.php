<?php

namespace RiseTechApps\Media;

use Illuminate\Support\ServiceProvider;
use RiseTechApps\Media\Features\PathGenerator\DefaultPathGenerator;
use RiseTechApps\Media\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     * Este método é executado em cada requisição e comando.
     */
    public function boot(): void
    {
        // Carrega as migrations da biblioteca.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Lógica que só roda em comandos de console (ex: php artisan).
        if ($this->app->runningInConsole()) {
            // Permite que o usuário publique o arquivo de configuração.
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('media.php'),
            ], 'config');

        }

        $this->registerPrefixedMediaDisk();

        config(['media-library.disk_name' => 'media_prefixed_disk']);

        config(['media-library.media_model' => \RiseTechApps\Media\Models\Media::class]);
        config(['media-library.path_generator' => DefaultPathGenerator::class]);

        $image_generators = config('media-library.image_generators', []);
        $image_generators[] = \RiseTechApps\Media\Features\Conversions\DefaultMediaConversion::class;
        config(['media-library.image_generators' => $image_generators]);

        Media::observe(new MediaObserver);
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Mescla a configuração padrão da biblioteca com a da aplicação.
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'media');
    }

    /**
     * Registra um disco de armazenamento dinâmico para a biblioteca.
     *
     * Esta função cria um novo disco em memória ('media_prefixed_disk') que é um clone
     * do disco base da aplicação (ex: 's3' ou 'local'), mas com a adição de um
     * prefixo no caminho 'root'. É extremamente performático e não interfere
     * com outros discos da aplicação.
     */
    protected function registerPrefixedMediaDisk(): void
    {
        $baseDiskName = config('media.base_disk') ?? config('filesystems.default');

        $baseDiskConfig = config("filesystems.disks.{$baseDiskName}");
        if (!$baseDiskConfig) {
            return;
        }

        $prefix = config('media.disk.prefix', 'uploads');

        $originalRoot = $baseDiskConfig['root'] ?? '';
        $separator = ($originalRoot && $prefix) ? '/' : '';
        $baseDiskConfig['root'] = $originalRoot . $separator . $prefix;

        config(['filesystems.disks.media_prefixed_disk' => $baseDiskConfig]);
    }
}
