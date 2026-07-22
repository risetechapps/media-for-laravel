<?php

namespace RiseTechApps\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Support\Uploads\MediaUploadService;

class SyncUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        protected Model $model,
        protected array $uploads,
        protected string $collectionName = 'uploads',
    ) {
    }

    public function handle(MediaUploadService $service): void
    {
        $service->sync($this->model, $this->uploads, $this->collectionName);
    }
}
