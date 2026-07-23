<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\MediaUploadTemporary;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

/*
 * Limpeza automática (model:prune). Uploads temporários abandonados e mídia na
 * lixeira além do prazo saem do banco E do disco.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->model = TestModel::query()->create(['name' => 'host']);
});

it('remove upload temporário abandonado e seus arquivos', function () {
    $temp = MediaUploadTemporary::query()->create();
    $temp->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    $path = $temp->getFirstMedia('uploads')->originalFile()->path;

    // Envelhece além do prazo (2 dias).
    $temp->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

    Artisan::call('model:prune', ['--model' => [MediaUploadTemporary::class]]);

    expect(MediaUploadTemporary::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('mantém upload temporário dentro do prazo', function () {
    $temp = MediaUploadTemporary::query()->create();
    $temp->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    Artisan::call('model:prune', ['--model' => [MediaUploadTemporary::class]]);

    expect(MediaUploadTemporary::query()->count())->toBe(1);
});

it('remove mídia na lixeira além do prazo, com os arquivos', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');
    $path = $media->originalFile()->path;

    $media->delete(); // soft
    $media->forceFill(['deleted_at' => now()->subDays(181)])->saveQuietly();

    Artisan::call('model:prune', ['--model' => [Media::class]]);

    expect(Media::withTrashed()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('mantém mídia na lixeira dentro do prazo', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    $media->delete(); // soft, recém-excluída

    Artisan::call('model:prune', ['--model' => [Media::class]]);

    expect(Media::withTrashed()->count())->toBe(1);
});
