<?php

namespace RiseTechApps\Media;

use Illuminate\Support\Facades\Route;
use RiseTechApps\Media\Http\Controllers\UploadController;

class Media
{
    public static function routes(array $options = []): void
    {
        Route::group($options, function (){

            Route::withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class . ':api')
                ->post('/uploads', [UploadController::class, 'upload']);
        });
    }
}
