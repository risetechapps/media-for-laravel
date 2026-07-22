<?php

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

/**
 * Registro físico de cada arquivo escrito em disco.
 *
 * Uma linha de `media` é o registro lógico; cada byte que existe no storage
 * (original, conversão, variante responsiva) tem sua própria linha aqui, com
 * o tamanho real. É isso que permite somar o storage de verdade — a coluna
 * `media.size` sozinha cobre apenas o arquivo original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function ($table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();

            // 'original' | 'conversion:{nome}' | 'responsive:{largura}'
            $table->string('variant');

            $table->string('disk');
            $table->text('path');
            $table->unsignedBigInteger('size');

            $table->timestamps();

            // Um arquivo por variante por mídia.
            $table->unique(['media_id', 'variant']);

            // Somatórios por disco e reconciliação com o bucket.
            $table->index('disk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
