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
