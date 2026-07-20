<?php

namespace RiseTechApps\Media\Features\Uploads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RiseTechApps\Media\Jobs\ManagerUploadsJob;
use RiseTechApps\Media\Models\MediaUploadTemporary;

class MediaUploadService
{
    public function handleProfile(Model $model, ?array $photo = null): void
    {
        if (is_null($photo)) {
            return;
        }

        $id = $photo['id'] ?? null;

        if ($id && Str::isUuid($id)) {
            $temporaryUpload = MediaUploadTemporary::find($id);
            if ($temporaryUpload) {

                $media = $temporaryUpload->getFirstMedia('*');
                $model->addMediaFromDisk($media->getPathRelativeToRoot(), $media->disk)
                    ->toMediaCollection($media->collection_name);
                $temporaryUpload->delete();
            }
        }
    }

    public function handleUploadsJob(Model $model, array $uploads): void
    {
        dispatch(new ManagerUploadsJob($model, $uploads));
    }

    public function handleUploads(Model $model, array $uploads): void
    {
        $uploads = collect($uploads ?? []);

        // Itens novos = ids de uploads temporários (UUID). Move do disco em vez de
        // re-baixar pela URL assinada (evita egress, expiração da URL e transferência dupla).
        $addedIds = [];

        $uploads
            ->filter(fn($item) => !is_numeric($item['id']))
            ->chunk(50)
            ->each(function ($chunk) use ($model, &$addedIds) {
                foreach ($chunk as $upload) {
                    $temporaryUpload = MediaUploadTemporary::find($upload['id']);
                    if (!$temporaryUpload) {
                        continue;
                    }

                    $media = $temporaryUpload->getFirstMedia('*');
                    if ($media) {
                        $newMedia = $model->addMediaFromDisk($media->getPathRelativeToRoot(), $media->disk)
                            ->toMediaCollection($media->collection_name);
                        $addedIds[] = $newMedia->getKey();
                    }

                    $temporaryUpload->delete();
                }
                gc_collect_cycles();
            });

        // Itens existentes = ids de mídia final (BIGINT). Preserva os já mantidos e os
        // recém-anexados; remove da coleção 'uploads' apenas o que saiu da seleção.
        $mediaIdsToKeep = $uploads
            ->filter(fn($item) => is_numeric($item['id']))
            ->pluck('id')
            ->map(fn($id) => (int)$id)
            ->merge($addedIds)
            ->toArray();

        $model->getMedia('uploads')
            ->reject(fn($media) => in_array($media->id, $mediaIdsToKeep))
            ->each->delete();
    }
}
