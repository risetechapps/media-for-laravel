<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Features\Uploads\MediaUploadService;
use RiseTechApps\Media\Models\MediaUploadTemporary;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Impede que as conversões (thumb, queued) rodem inline e exijam GD/spatie-image.
    Queue::fake();
    Storage::fake('media_prefixed_disk');

    $this->service = app(MediaUploadService::class);
    $this->model = TestModel::query()->create(['name' => 'host']);
});

/** Cria um upload temporário com um arquivo na coleção informada. */
function makeTemporaryUpload(string $collection = 'uploads'): MediaUploadTemporary
{
    $temp = MediaUploadTemporary::query()->create();
    $temp->addMedia(UploadedFile::fake()->image('file.jpg'))
        ->toMediaCollection($collection);

    return $temp->refresh();
}

it('move o upload temporário para o model e apaga o temporário', function () {
    $temp = makeTemporaryUpload('uploads');

    $payload = [
        ['id' => $temp->id, 'preview' => 'ignored', 'collection' => 'uploads'],
    ];

    $this->service->handleUploads($this->model, $payload);

    expect($this->model->getMedia('uploads'))->toHaveCount(1)
        ->and(MediaUploadTemporary::query()->count())->toBe(0);
});

it('mantém mídia existente reenviada e remove a que saiu da seleção', function () {
    $keep = $this->model->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('uploads');
    $drop = $this->model->addMedia(UploadedFile::fake()->image('drop.jpg'))->toMediaCollection('uploads');

    // payload só reenvia a mídia 'keep' (id numérico) → 'drop' deve sumir.
    $payload = [
        ['id' => (string) $keep->id, 'preview' => 'ignored', 'collection' => 'uploads'],
    ];

    $this->service->handleUploads($this->model->fresh(), $payload);

    $ids = $this->model->fresh()->getMedia('uploads')->pluck('id')->all();

    expect($ids)->toContain($keep->id)
        ->not->toContain($drop->id);
});

it('preserva a mídia recém-anexada ao reconciliar remoções', function () {
    $temp = makeTemporaryUpload('uploads');

    // payload só traz o item novo (UUID). Nenhum id numérico → keep list vazia.
    // A mídia anexada não pode ser apagada pelo reconcile.
    $payload = [
        ['id' => $temp->id, 'preview' => 'ignored', 'collection' => 'uploads'],
    ];

    $this->service->handleUploads($this->model, $payload);

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(1);
});

it('ignora id de temporário inexistente sem erro', function () {
    $payload = [
        ['id' => (string) \Illuminate\Support\Str::uuid(), 'preview' => 'ignored', 'collection' => 'uploads'],
    ];

    $this->service->handleUploads($this->model, $payload);

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(0);
});

it('esvazia a coleção uploads quando o payload é vazio', function () {
    // Comportamento intencional: payload vazio = nenhuma seleção → reconcile remove tudo.
    // Os callers (ex: ClientsController) guardam com `if (count($uploads) > 0)` antes de
    // despachar o Job, então esse caminho não é acionado com coleção populada em produção.
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    $this->service->handleUploads($this->model->fresh(), []);

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(0);
});
