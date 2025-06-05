<?php

namespace RiseTechApps\Media\Features\Uploads;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RiseTechApps\Media\Jobs\ManagerUploadsJob;
use RiseTechApps\Media\Models\MediaUploadTemporary;

class MediaUploadService
{
    public function handleUpload(Model $model, string $type): void
    {

        $uploads = request()->input($type);

        DB::beginTransaction();
        try {
            $processedTemporaryIds = [];

            foreach ($uploads as $key => $upload) {

                $temporaryUpload = MediaUploadTemporary::find($upload['id']);
                if ($temporaryUpload) {
                    $processedTemporaryIds[] = $this->moveTemporaryMediaToModel($model, $temporaryUpload);
                    unset($uploads[$key]);
                }
            }

            $this->removeUnusedMedia($model, array_column($uploads, 'id'), $type, $processedTemporaryIds);

            DB::commit();
        } catch (Exception $e) {

            DB::rollBack();
            logglyError()->performedOn($model)->withProperties(['type' => $type])->exception($exception)->log("Error processing temporary media");
        }
    }

    /**
     * Move o upload temporário para o modelo final de mídia.
     *
     * @param Model $model O modelo que usará o Spatie Media Library
     * @param MediaUploadTemporary $temporaryUpload O upload temporário
     * @param string $collectionName Nome da coleção de mídia
     * @return string
     */

    private function moveTemporaryMediaToModel(Model $model, MediaUploadTemporary $temporaryUpload): string
    {
        $media = $temporaryUpload->getFirstMedia('*');
        $_media = $model->addMediaFromDisk($media->getPathRelativeToRoot(), $media->disk)
            ->toMediaCollection($media->collection_name);
        $temporaryUpload->delete();

        return $_media->getKey();

    }


    /**
     * Remove mídias que não estão mais associadas ao cliente durante a atualização.
     *
     * @param Model $model O modelo que usará o Spatie Media Library
     * @param array $uploadIds IDs dos uploads atuais enviados pelo frontend
     * @param string $collectionName Nome da coleção de mídia
     * @param array $processedTemporaryIds IDs de uploads temporários já processados
     */

    private function removeUnusedMedia(Model $model, array $uploadIds, string $collectionName, array $processedTemporaryIds): void
    {
        $currentMedia = $model->getMedia($collectionName);

        foreach ($currentMedia as $media) {
            if (!in_array($media->id, $uploadIds) && !in_array($media->id, $processedTemporaryIds)) {
                $media->delete();
            }
        }
    }

    public function handleProfile(Model $model): void
    {
        $uploads = request()->input('photo');

        if (!is_null($uploads)) {
            $id = $uploads['id'];

            if (Str::isUuid($id)) {
                $temporaryUpload = MediaUploadTemporary::find($id);
                if ($temporaryUpload) {

                    $media = $temporaryUpload->getFirstMedia('*');
                    $model->addMediaFromDisk($media->getPathRelativeToRoot(), $media->disk)
                        ->toMediaCollection($media->collection_name);
                    $temporaryUpload->delete();
                }
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

        $mediaIdsToKeep = $uploads
            ->filter(fn($item) => is_numeric($item['id']))
            ->pluck('id')
            ->map(fn($id) => (int)$id)
            ->toArray();

        $model->getMedia('uploads')
            ->reject(fn($media) => in_array($media->id, $mediaIdsToKeep))
            ->each->delete();

        $newUploads = $uploads
            ->filter(fn($item) => !is_numeric($item['id']));

        foreach ($newUploads as $upload) {
            $model->addMediaFromUrl($upload['preview'])->toMediaCollection($upload['collection']);
            unset($upload);
            gc_collect_cycles();
        }
    }
}
