<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Contracts\QuotaResolver;
use RiseTechApps\Media\Exceptions\StorageQuotaExceeded;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\MediaFile;
use RiseTechApps\Media\Tests\Fixtures\TestModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $this->model = TestModel::query()->create(['name' => 'host']);
});

it('barra o upload que estoura a cota fixa e não deixa rastro', function () {
    config()->set('media.quota.default', 10); // 10 bytes

    $file = UploadedFile::fake()->create('big.bin', 5); // 5 KB >> 10 bytes

    expect(fn () => $this->model->addMedia($file)->toMediaCollection('uploads'))
        ->toThrow(StorageQuotaExceeded::class);

    // Nada em disco nem no banco — a checagem roda antes de gravar.
    expect(Media::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('permite o upload dentro da cota', function () {
    config()->set('media.quota.default', '10GB');

    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    expect(Media::query()->count())->toBe(1);
});

it('é ilimitado quando não há cota configurada', function () {
    // media.quota.default = null (padrão) e sem resolver.
    $this->model->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('uploads');

    expect(Media::query()->count())->toBe(1);
});

it('o resolver vence o default do config', function () {
    config()->set('media.quota.default', '10GB'); // generoso

    app()->bind(QuotaResolver::class, fn () => new class implements QuotaResolver {
        public function limitInBytes(): ?int
        {
            return 10; // apertado
        }
    });

    $file = UploadedFile::fake()->create('big.bin', 5);

    expect(fn () => $this->model->addMedia($file)->toMediaCollection('uploads'))
        ->toThrow(StorageQuotaExceeded::class);
});

it('acumula o uso: o upload que ultrapassa o restante é barrado', function () {
    // Cabe o primeiro arquivo (1024 bytes), não a soma dos dois.
    config()->set('media.quota.default', 1500);

    $this->model->addMedia(UploadedFile::fake()->create('a.bin', 1))->toMediaCollection('uploads');

    expect(fn () => $this->model->addMedia(UploadedFile::fake()->create('b.bin', 1))->toMediaCollection('uploads'))
        ->toThrow(StorageQuotaExceeded::class);

    expect(Media::query()->count())->toBe(1);
});
