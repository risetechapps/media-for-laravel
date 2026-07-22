<?php

namespace RiseTechApps\Media\Support\Uploads;

use Illuminate\Database\Eloquent\Model;
use RiseTechApps\Media\Jobs\SyncUploadsJob;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\MediaUploadTemporary;

/**
 * Vincula ao model definitivo os arquivos enviados como upload temporário e
 * remove os que saíram da seleção.
 *
 * O cliente devolve a lista de uploads como veio da tela: itens novos trazem o
 * id do upload temporário, itens já existentes trazem o id da mídia. A
 * distinção é feita pela existência do temporário — não por formato de id, que
 * é uuid nos dois casos.
 */
class MediaUploadService
{
    /**
     * @param  array<int, array{id: string}>  $uploads
     */
    public function sync(Model $model, array $uploads, string $collectionName = 'uploads'): void
    {
        $keep = [];

        // Anexa antes de remover: se algo falhar no meio, nada foi apagado e a
        // operação pode ser repetida sem perda.
        foreach ($uploads as $upload) {
            $id = $upload['id'] ?? null;

            if (blank($id)) {
                continue;
            }

            $temporary = MediaUploadTemporary::query()->find($id);

            if (! $temporary) {
                // Mídia já vinculada; permanece.
                $keep[] = $id;

                continue;
            }

            if ($media = $temporary->media()->first()) {
                $keep[] = $this->attach($model, $media)->getKey();
            }

            $temporary->delete();
        }

        $this->removeUnselected($model, $collectionName, $keep);
    }

    public function syncQueued(Model $model, array $uploads, string $collectionName = 'uploads'): void
    {
        dispatch(new SyncUploadsJob($model, $uploads, $collectionName));
    }

    /**
     * Move o arquivo do dono provisório para o definitivo.
     *
     * A cópia é feita disco a disco a partir do caminho já armazenado — sem
     * baixar o conteúdo nem depender de URL assinada, que expiraria enquanto o
     * job aguarda na fila.
     */
    protected function attach(Model $model, Media $media): Media
    {
        return $model->addMediaFromDisk($media->getPathRelativeToRoot(), $media->disk)
            ->usingName($media->name)
            ->usingFileName($media->file_name)
            ->withCustomProperties($media->custom_properties ?? [])
            ->toMediaCollection($media->collection_name);
    }

    /**
     * @param  array<int, string>  $keep  ids que permanecem (mantidos + recém-anexados)
     */
    protected function removeUnselected(Model $model, string $collectionName, array $keep): void
    {
        $model->media()
            ->inCollection($collectionName)
            ->whereNotIn('id', $keep ?: ['-'])
            ->cursor()
            ->each(fn (Media $media) => $media->delete());
    }
}
