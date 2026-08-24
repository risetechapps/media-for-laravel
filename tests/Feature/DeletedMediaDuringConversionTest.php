<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Exceptions\MediaNoLongerExists;
use RiseTechApps\Media\Jobs\PerformConversionsJob;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Conversions\Conversion;
use RiseTechApps\Media\Support\Conversions\ConversionEngine;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

/*
 * Corrida entre um derivado em fila e a exclusão definitiva da mídia.
 *
 * Coleção singleFile apaga a anterior com forceDelete: se o job de conversão
 * dela já estiver rodando, o insert em media_files esbarra na foreign key
 * (SQLSTATE 23503) e ainda deixa o derivado pago no storage. O caminho de
 * escrita precisa desistir em silêncio e não deixar bytes para trás.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->model = TestModel::query()->create(['name' => 'host']);
});

function derivedFileOfBytes(int $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'media-race') . '.webp';
    file_put_contents($path, str_repeat('x', $bytes));

    return $path;
}

it('reconhece mídia na lixeira e mídia apagada em definitivo', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $media->delete();

    // Soft delete mantém os arquivos: a conversão pendente continua válida.
    expect($media->stillExists())->toBeTrue();

    $media->forceDelete();

    expect($media->stillExists())->toBeFalse();
});

it('não grava conversão de mídia apagada nem deixa o arquivo no disco', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $media->forceDelete();

    $filesystem = app(MediaFilesystem::class);

    expect(fn () => $filesystem->storeConversion($media, 'thumb', derivedFileOfBytes(6732)))
        ->toThrow(MediaNoLongerExists::class);

    // O derivado chegou a subir; o caminho de escrita tem de removê-lo, senão
    // fica byte pago sem linha em media_files.
    $expected = "uploads/{$media->getKey()}/conversions/";

    expect(collect(Storage::disk('local')->allFiles())->filter(
        fn (string $path) => str_starts_with($path, $expected)
    ))->toBeEmpty();
});

it('conversão enfileirada vira no-op quando a mídia sumiu', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $media->forceDelete();

    $engine = app(ConversionEngine::class);

    $engine->perform($media, [Conversion::make('thumb')->width(50)]);

    expect(Media::query()->unscoped()->withTrashed()->whereKey($media->getKey())->exists())->toBeFalse();

    // handle() não pode estourar: o job só perdeu o destino.
    (new PerformConversionsJob($media, ['thumb']))->handle($engine);
})->throwsNoExceptions();

it('troca em coleção singleFile apaga a anterior em definitivo', function () {
    $first = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('avatar');

    $this->model->addMedia(UploadedFile::fake()->image('b.jpg'))
        ->toMediaCollection('avatar');

    // É esta exclusão que deixava o job da primeira sem destino.
    expect($first->stillExists())->toBeFalse();
});
