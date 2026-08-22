<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

/*
 * Escopo por contexto (tenancy desacoplado). O ponto de segurança é o
 * fail-closed: sem contexto, nunca se enxerga a mídia de outro contexto.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->model = TestModel::query()->create(['name' => 'host']);
});

/** Define o contexto atual em runtime. */
function scopeTo(array $context): void
{
    app(MediaScopeManager::class)->resolveUsing(fn () => $context);
}

it('carimba o contexto em custom_properties._scope na criação', function () {
    scopeTo(['sub_tenant_id' => 1]);

    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    expect($media->fresh()->custom_properties['_scope'])->toBe(['sub_tenant_id' => 1]);
});

it('filtra por contexto: cada um vê só a sua mídia', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    scopeTo(['sub_tenant_id' => 2]);
    $this->model->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('uploads');

    scopeTo(['sub_tenant_id' => 1]);
    expect(Media::query()->count())->toBe(1);

    scopeTo(['sub_tenant_id' => 2]);
    expect(Media::query()->count())->toBe(1);
});

it('fail-closed: contexto vazio só enxerga mídia sem escopo', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    // Mídia global: criada sem contexto, não recebe _scope.
    scopeTo([]);
    $this->model->addMedia(UploadedFile::fake()->image('g.jpg'))->toMediaCollection('uploads');

    // Contexto vazio → só a global.
    scopeTo([]);
    expect(Media::query()->count())->toBe(1);

    // Contexto 1 → só a do tenant 1; a global não aparece.
    scopeTo(['sub_tenant_id' => 1]);
    expect(Media::query()->count())->toBe(1);
});

it('unscoped() ignora a partição e vê tudo', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    scopeTo(['sub_tenant_id' => 2]);
    $this->model->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('uploads');

    expect(Media::unscoped()->count())->toBe(2);
});

/*
 * Exclusão fura o escopo de propósito: linha escondida nunca receberia delete(),
 * o hook de limpeza não rodaria e o arquivo ficaria pago para sempre.
 */

it('deleteAllMedia apaga a mídia de outro contexto junto com o dono', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $um = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    scopeTo(['sub_tenant_id' => 2]);
    $dois = $this->model->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('uploads');

    // Sem contexto: sob fail-closed, nenhuma das duas apareceria numa query normal.
    scopeTo([]);
    $this->model->fresh()->deleteAllMedia();

    expect(Media::unscoped()->withTrashed()->whereKey($um->id)->first()->deleted_at)->not->toBeNull()
        ->and(Media::unscoped()->withTrashed()->whereKey($dois->id)->first()->deleted_at)->not->toBeNull();
});

it('singleFile remove a anterior mesmo com o contexto trocado', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $antiga = $this->model->addMedia(UploadedFile::fake()->image('velha.jpg'))->toMediaCollection('avatar');

    scopeTo(['sub_tenant_id' => 2]);
    $this->model->fresh()->addMedia(UploadedFile::fake()->image('nova.jpg'))->toMediaCollection('avatar');

    // removePreviousMedia usa forceDelete: a anterior não fica nem na lixeira.
    expect(Media::unscoped()->withTrashed()->whereKey($antiga->id)->exists())->toBeFalse();
});

it('removeUnselected apaga o que saiu da seleção mesmo sem contexto', function () {
    scopeTo(['sub_tenant_id' => 1]);
    $sai = $this->model->addMedia(UploadedFile::fake()->image('sai.jpg'))->toMediaCollection('uploads');

    scopeTo([]);
    app(\RiseTechApps\Media\Support\Uploads\MediaUploadService::class)
        ->sync($this->model->fresh(), [], 'uploads');

    expect(Media::unscoped()->withTrashed()->whereKey($sai->id)->first()->deleted_at)->not->toBeNull();
});
