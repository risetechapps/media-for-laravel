<?php

namespace RiseTechApps\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use RiseTechApps\Media\Http\UploadController;
use RiseTechApps\Media\Support\Uploads\MediaUploadService;

class Media
{
    public function __construct(protected MediaUploadService $uploadService)
    {
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
