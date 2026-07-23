<?php

namespace RiseTechApps\Media\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RiseTechApps\Media\MediaServiceProvider;

class TestCase extends Orchestra
{
    /**
     * Providers necessários para o package funcionar sob o Testbench.
     *
     * OBS: monitoring/risetools/has-uuid são dependências privadas. Em execução dentro
     * do monorepo (server.app.br) elas já estão instaladas e seus providers são
     * descobertos via package discovery. Se algum provider precisar de registro
     * explícito, adicione aqui. Confirme os nomes das classes conforme cada package.
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Tpetry\PostgresqlEnhanced\PostgresqlEnhancedServiceProvider::class,
            MediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Conexão padrão vem do phpunit.xml (pgsql). O disco base é 'local'; o package
        // clona ele em 'media_prefixed_disk'. Nos testes usamos Storage::fake nesse disco.
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('media.disk.prefix', '');
    }

    protected function defineRoutes($router): void
    {
        \RiseTechApps\Media\Media::routes();
    }

    /**
     * Cria a tabela do model host de teste após rodar as migrations do package.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
}
