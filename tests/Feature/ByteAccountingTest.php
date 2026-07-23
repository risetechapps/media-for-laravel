<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\MediaFile;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

/*
 * A invariante do package: MediaFilesystem é o único caminho de bytes. Toda
 * escrita grava o arquivo, registra a linha em media_files e atualiza
 * total_size; toda remoção inverte. Estes testes blindam essa contagem.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();                 // não roda conversões enfileiradas
    Storage::fake('local');
    $this->model = TestModel::query()->create(['name' => 'host']);
});

/** Cria um arquivo local de bytes conhecidos e devolve o caminho. */
function localFileOfBytes(int $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'media-test');
    file_put_contents($path, str_repeat('x', $bytes));

    return $path;
}

it('registra o original e total_size = tamanho do arquivo', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $original = $media->originalFile();

    expect($original)->not->toBeNull()
        ->and($original->variant)->toBe(MediaFile::VARIANT_ORIGINAL)
        ->and($media->files()->count())->toBe(1)
        ->and((int) $media->total_size)->toBe((int) $original->size)
        ->and((int) $media->total_size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($original->path);
});

it('soma cada variante no total_size', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $originalTotal = (int) $media->fresh()->total_size;

    app(MediaFilesystem::class)->storeConversion($media, 'thumb', localFileOfBytes(1234));

    expect((int) $media->fresh()->total_size)->toBe($originalTotal + 1234)
        ->and($media->fresh()->files()->count())->toBe(2);
});

it('remover uma variante reverte o total_size', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $originalTotal = (int) $media->fresh()->total_size;

    app(MediaFilesystem::class)->storeConversion($media, 'thumb', localFileOfBytes(500));
    app(MediaFilesystem::class)->deleteVariant($media, MediaFile::variantForConversion('thumb'));

    expect((int) $media->fresh()->total_size)->toBe($originalTotal)
        ->and($media->fresh()->files()->count())->toBe(1);
});

it('exclusão definitiva remove arquivos e registros', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $path = $media->originalFile()->path;

    $media->forceDelete();

    Storage::disk('local')->assertMissing($path);

    expect(Media::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

it('soft delete mantém os arquivos em disco', function () {
    $media = $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))
        ->toMediaCollection('uploads');

    $path = $media->originalFile()->path;

    $media->delete(); // soft

    Storage::disk('local')->assertExists($path);

    expect(MediaFile::query()->count())->toBe(1);
});
