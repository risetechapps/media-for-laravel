<?php

namespace RiseTechApps\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use RiseTechApps\Media\Features\Uploads\MediaUploadService;
use RiseTechApps\Media\Http\Controllers\UploadController;

class Media
{
    public function __construct(protected MediaUploadService $uploadService)
    {
    }

    public static function routes(array $options = []): void
    {
        Route::group($options, function (){

            Route::post('/uploads', [UploadController::class, 'upload']);
        });
    }

    /**
     * Despacha o processamento de uploads para o modelo em background.
     */
    public function handleUploads(Model $model, array $uploads): void
    {
        $this->uploadService->handleUploadsJob($model, $uploads);
    }

    /**
     * Processa a foto de perfil do modelo a partir do request atual.
     */
    public function handleProfile(Model $model): void
    {
        $this->uploadService->handleProfile($model);
    }
}
