<?php

namespace RiseTechApps\Media\Support\Uploads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
 * é uuid nos dois casos. Item já vinculado permanece como está: o arquivo não
 * é recriado a cada update do model.
 */
class MediaUploadService
{
    /**
     * @param  array<int, array{id: string}>|array{id: string}|string|null  $uploads
     */
    public function sync(Model $model, array|string|null $uploads, string $collectionName = 'uploads'): void
    {
        // Campo ausente não é seleção vazia: null preserva a coleção, [] esvazia.
        if ($uploads === null) {
            return;
        }

        $keep = [];

        // Anexa antes de remover: se algo falhar no meio, nada foi apagado e a
        // operação pode ser repetida sem perda.
        foreach ($this->normalize($uploads) as $id) {
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

    /**
     * @param  array<int, array{id: string}>|array{id: string}|string|null  $uploads
     */
    public function syncQueued(Model $model, array|string|null $uploads, string $collectionName = 'uploads'): void
    {
        dispatch(new SyncUploadsJob($model, $uploads, $collectionName));
    }

    /**
     * Reduz a seleção aos ids, aceitando as formas em que o cliente a devolve:
     * lista de itens, item único (campo de arquivo só) ou id solto.
     *
     * Id fora do formato uuid é descartado aqui — chegaria cru na query e o
     * Postgres derruba a transação com 22P02.
     *
     * @param  array<mixed>|string  $uploads
     * @return array<int, string>
     */
    protected function normalize(array|string $uploads): array
    {
        if (is_string($uploads)) {
            $uploads = [['id' => $uploads]];
        } elseif ($uploads !== [] && ! array_is_list($uploads)) {
            $uploads = [$uploads];
        }

        $ids = [];

        foreach ($uploads as $upload) {
            $id = is_array($upload) ? ($upload['id'] ?? null) : $upload;

            if (Str::isUuid($id)) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
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
     * A exclusão fura o global scope de propósito: o recorte já vem da
     * posse (a relação é de uma instância específica, alcançada depois de auth
     * e tenancy). O filtro por cima não protege nada e ainda pode esconder
     * linha — e linha escondida nunca recebe delete(), logo o hook que limpa o
     * disco não roda e o arquivo fica pago para sempre.
     *
     * @param  array<int, string>  $keep  ids que permanecem (mantidos + recém-anexados)
     */
    protected function removeUnselected(Model $model, string $collectionName, array $keep): void
    {
        $model->media()
            ->unscoped()
            ->inCollection($collectionName)
            ->whereNotIn('id', $keep)
            ->cursor()
            ->each(fn (Media $media) => $media->delete());
    }
}
