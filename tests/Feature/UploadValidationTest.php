<?php

use Illuminate\Http\UploadedFile;

/*
 * Testa a validação do endpoint /uploads. Roda ANTES do try/catch do controller,
 * então dispara ValidationException (422) sem depender das macros de resposta
 * (jsonSuccess/jsonGone) nem do banco. Não usa RefreshDatabase de propósito.
 */

it('rejeita requisição sem arquivo com 422', function () {
    $this->postJson('/uploads', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('rejeita arquivo acima do tamanho máximo', function () {
    config()->set('media.upload.max_size', 100); // 100 KB

    $file = UploadedFile::fake()->create('big.pdf', 500); // 500 KB

    $this->postJson('/uploads', ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('rejeita collection não-string', function () {
    $file = UploadedFile::fake()->image('a.jpg');

    $this->postJson('/uploads', ['file' => $file, 'collection' => ['array']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('collection');
});
