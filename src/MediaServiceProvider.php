<?php

namespace RiseTechApps\Media;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RiseTechApps\Media\Contracts\PathGeneratorContract;
use RiseTechApps\Media\Contracts\UrlGeneratorContract;
use RiseTechApps\Media\Events\MediaHasBeenAdded;
use RiseTechApps\Media\Listeners\GenerateConversions;
use RiseTechApps\Media\Support\Disk\MediaDisk;
use RiseTechApps\Media\Support\PathGenerator\DefaultPathGenerator;
use RiseTechApps\Media\Support\Urls\DefaultUrlGenerator;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    #[\Override]
    public function register(): void
    {
        // Mescla a configuração padrão da biblioteca com a da aplicação.
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'media');

        // Gerador de caminhos: trocável via config para layouts customizados.
        $this->app->bind(PathGeneratorContract::class, function ($app) {
            return $app->make(config('media.path_generator', DefaultPathGenerator::class));
        });

        // Gerador de URLs: trocável via config para servir por CDN.
        $this->app->bind(UrlGeneratorContract::class, function ($app) {
            return $app->make(config('media.url_generator', DefaultUrlGenerator::class));
        });

        // Vincula a classe Media ao container sob a chave 'media' (usada pela Facade).
        $this->app->singleton('media', fn ($app) => $app->make(Media::class));
    }

    /**
     * Bootstrap the application services.
     * Este método é executado em cada requisição e comando.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerPrefixedMediaDisk();

        // Lógica que só roda em comandos de console (ex: php artisan).
        if ($this->app->runningInConsole()) {
            // Permite que o usuário publique o arquivo de configuração.
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('media.php'),
            ], 'config');
        }

        Event::listen(MediaHasBeenAdded::class, GenerateConversions::class);

        AliasLoader::getInstance()->alias('Media', MediaFacade::class);
    }

    /**
     * Registra um disco de armazenamento dinâmico para a biblioteca.
     *
     * Cria um disco em memória ('media_prefixed_disk') que é um clone do disco
     * base da aplicação (ex: 's3' ou 'local'), com um prefixo acrescentado ao
     * 'root'. Isola os arquivos de mídia dentro do storage já existente, sem
     * exigir bucket novo e sem interferir nos demais discos.
     */
    protected function registerPrefixedMediaDisk(): void
    {
        if (! MediaDisk::hasPrefix()) {
            return;
        }

        $baseConfig = config('filesystems.disks.' . MediaDisk::baseName());

        if (! $baseConfig) {
            return;
        }

        $root = rtrim((string) ($baseConfig['root'] ?? ''), '/');

        $baseConfig['root'] = $root === ''
            ? MediaDisk::prefix()
            : $root . '/' . MediaDisk::prefix();

        config(['filesystems.disks.' . MediaDisk::PREFIXED => $baseConfig]);
    }
}
