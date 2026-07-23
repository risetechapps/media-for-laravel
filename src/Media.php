<?php

namespace RiseTechApps\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use RiseTechApps\Media\Http\UploadController;
use RiseTechApps\Media\Support\Quota\Quota;
use RiseTechApps\Media\Support\Reports\StorageReport;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;
use RiseTechApps\Media\Support\Uploads\MediaUploadService;

class Media
{
    public function __construct(protected MediaUploadService $uploadService)
    {
    }

    /**
     * Ponto de entrada dos relatórios de storage (global, ignora o escopo).
     *
     *   Media::storage()->total();
     *   Media::storage()->byCollection();
     *   Media::storage()->forModel($user);
     */
    public function storage(): StorageReport
    {
        return app(StorageReport::class);
    }

    /**
     * Cota do contexto atual (respeita o escopo do resolver).
     *
     *   Media::quota()->usage();
     *   Media::quota()->remaining();
     *   Media::quota()->exceeded();
     */
    public function quota(): Quota
    {
        return app(Quota::class);
    }

    /**
     * Define o resolver de contexto em runtime, sem precisar de config.
     *
     *   Media::resolveScopeUsing(fn () => ['sub_tenant_id' => 42]);
     */
    public function resolveScopeUsing(callable $resolver): void
    {
        app(MediaScopeManager::class)->resolveUsing($resolver);
    }

    /**
     * Registra o endpoint de upload temporário.
     *
     * Middleware, prefixo e nomes ficam por conta de quem instala — passe o que
     * precisar em $options (autenticação e limite de requisições, por exemplo).
     */
    public static function routes(array $options = []): void
    {
        Route::group($options, function () {
            Route::post('/uploads', [UploadController::class, 'upload']);
        });
    }

    /**
     * Vincula os uploads ao model, em background.
     */
    public function syncUploads(Model $model, array $uploads, string $collectionName = 'uploads'): void
    {
        $this->uploadService->syncQueued($model, $uploads, $collectionName);
    }

    /**
     * Vincula os uploads ao model imediatamente.
     */
    public function syncUploadsNow(Model $model, array $uploads, string $collectionName = 'uploads'): void
    {
        $this->uploadService->sync($model, $uploads, $collectionName);
    }
}
