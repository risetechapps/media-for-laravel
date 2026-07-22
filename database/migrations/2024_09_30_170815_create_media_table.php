<?php

use Illuminate\Database\Migrations\Migration;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

/**
 * Registro lógico de uma mídia.
 *
 * `size`       = arquivo original.
 * `total_size` = original + conversões + variantes responsivas, denormalizado
 *                de `media_files` e mantido pelo serviço de Filesystem.
 *
 * Não existe coluna `generated_conversions`: a existência de uma conversão é
 * derivada de `media_files` (linha com variant = 'conversion:{nome}'), evitando
 * estado duplicado que pode divergir do disco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::createExtensionIfNotExists('citext');
        }

        Schema::create('media', function ($table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('model');

            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();

            $table->string('disk');
            $table->string('conversions_disk')->nullable();

            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('total_size')->default(0);

            $table->jsonb('manipulations');
            $table->jsonb('custom_properties');
            $table->jsonb('responsive_images');

            $table->unsignedInteger('order_column')->nullable();

            $table->softDeletes();
            $table->nullableTimestamps();

            $table->index('collection_name');
            $table->index('name');
            $table->index('file_name');
            $table->index('order_column');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
