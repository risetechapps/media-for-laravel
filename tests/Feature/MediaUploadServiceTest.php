<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RiseTechApps\Media\Models\MediaUploadTemporary;
use RiseTechApps\Media\Support\Uploads\MediaUploadService;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');

    $this->service = app(MediaUploadService::class);
    $this->model = TestModel::query()->create(['name' => 'host']);
});

/** Cria um upload temporário com um arquivo na coleção informada. */
function makeTemporaryUpload(string $collection = 'uploads'): MediaUploadTemporary
{
    $temp = MediaUploadTemporary::query()->create();
    $temp->addMedia(UploadedFile::fake()->image('file.jpg'))->toMediaCollection($collection);

    return $temp->refresh();
}

it('move o upload temporário para o model e apaga o temporário', function () {
    $temp = makeTemporaryUpload('uploads');

    $this->service->sync($this->model, [['id' => $temp->id]], 'uploads');

    expect($this->model->getMedia('uploads'))->toHaveCount(1)
        ->and(MediaUploadTemporary::query()->count())->toBe(0);
});

it('mantém mídia existente reenviada e remove a que saiu da seleção', function () {
    $keep = $this->model->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('uploads');
    $drop = $this->model->addMedia(UploadedFile::fake()->image('drop.jpg'))->toMediaCollection('uploads');

    // Só 'keep' vem no payload (id de mídia, não de temporário) → 'drop' sai.
    $this->service->sync($this->model->fresh(), [['id' => (string) $keep->id]], 'uploads');

    $ids = $this->model->fresh()->getMedia('uploads')->pluck('id')->all();

    expect($ids)->toContain($keep->id)
        ->not->toContain($drop->id);
});

it('ignora id de temporário inexistente sem erro', function () {
    $this->service->sync($this->model, [['id' => (string) Str::uuid()]], 'uploads');

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(0);
});

it('esvazia a coleção quando o payload é vazio', function () {
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    $this->service->sync($this->model->fresh(), [], 'uploads');

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(0);
});

it('aceita item único vindo como objeto, não como lista', function () {
    $keep = $this->model->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('profile');
    $drop = $this->model->addMedia(UploadedFile::fake()->image('drop.jpg'))->toMediaCollection('profile');

    // Formato do campo de foto: um objeto só, com os demais campos do recurso.
    $this->service->sync($this->model->fresh(), [
        'id' => (string) $keep->id,
        'collection_name' => 'profile',
        'file_name' => 'keep.jpg',
        'size' => 1234,
    ], 'profile');

    $ids = $this->model->fresh()->getMedia('profile')->pluck('id')->all();

    expect($ids)->toContain($keep->id)
        ->not->toContain($drop->id);
});

it('aceita o id solto como string', function () {
    $keep = $this->model->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('uploads');

    $this->service->sync($this->model->fresh(), (string) $keep->id, 'uploads');

    expect($this->model->fresh()->getMedia('uploads')->pluck('id')->all())->toContain($keep->id);
});

it('preserva a coleção quando o campo não vem no payload', function () {
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    $this->service->sync($this->model->fresh(), null, 'uploads');

    expect($this->model->fresh()->getMedia('uploads'))->toHaveCount(1);
});

it('descarta id fora do formato uuid sem quebrar a query', function () {
    $keep = $this->model->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('uploads');

    // '-' era a sentinela antiga: em Postgres estourava 22P02 na coluna uuid.
    $this->service->sync($this->model->fresh(), [['id' => '-'], ['id' => (string) $keep->id]], 'uploads');

    expect($this->model->fresh()->getMedia('uploads')->pluck('id')->all())->toContain($keep->id);
});

it('não recria o arquivo de uma mídia já vinculada', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('same.jpg'))->toMediaCollection('uploads');

    $this->service->sync($this->model->fresh(), [['id' => (string) $media->id]], 'uploads');

    $fresh = $this->model->fresh()->getMedia('uploads');

    expect($fresh)->toHaveCount(1)
        ->and($fresh->first()->id)->toBe($media->id);
});
