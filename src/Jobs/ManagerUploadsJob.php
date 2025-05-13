<?php

namespace RiseTechApps\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Features\Uploads\MediaUploadService;

class ManagerUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Model $model;
    protected array $uploads;

    public function __construct(Model $model, array $uploads)
    {
        $this->model = $model;
        $this->uploads = $uploads;
    }

    public function handle(MediaUploadService $mediaUploadService): void
    {
        $mediaUploadService->handleUploads($this->model, $this->uploads);
    }
}
