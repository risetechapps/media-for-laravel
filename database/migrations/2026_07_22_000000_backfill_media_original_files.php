<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill do arquivo `original` em `media_files` para as mídias herdadas do
 * schema Spatie (v1/v2), preservando a contabilidade de bytes no upgrade v3.
 *
 * O caminho físico é DETERMINÍSTICO e igual ao que já está no disco, porque a
 * migration de upgrade promoveu a coluna `uuid` legada a PK `id` e o layout
 * do PathGenerator é `{collection}/{id}/{file_name}` — o mesmo `{collection}/
 * {uuid}/{file_name}` que o Spatie usava. Nada é lido do disco aqui: o tamanho
 * vem de `media.size` (o original), e só o original é registrado.
 *
 * Conversões e variantes responsivas NÃO entram aqui: seus tamanhos só existem
 * no disco. Rode o comando de reconciliação para registrá-las e somar seus
 * bytes ao `total_size`.
 *
 * Idempotente: só insere o `original` de quem ainda não tem, e recalcula
 * `total_size` a partir de `media_files` (a fonte da verdade). Em instalação
 * nova (media vazia) é no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('media') || ! Schema::hasTable('media_files')) {
            return;
        }

        // gen_random_uuid() é nativo no PG 13+; a extensão cobre versões antigas.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        // Registra o arquivo original de cada mídia que ainda não o tem.
        // path = trim(collection_name, '/') . '/' . id . '/' . file_name,
        // espelhando exatamente o DefaultPathGenerator.
        DB::statement(<<<'SQL'
            INSERT INTO media_files (id, media_id, variant, disk, path, size, created_at, updated_at)
            SELECT
                gen_random_uuid(),
                m.id,
                'original',
                m.disk,
                trim(both '/' from m.collection_name) || '/' || m.id || '/' || m.file_name,
                m.size,
                now(),
                now()
            FROM media m
            WHERE NOT EXISTS (
                SELECT 1 FROM media_files f
                WHERE f.media_id = m.id AND f.variant = 'original'
            )
        SQL);

        // total_size = soma real dos arquivos já registrados. Por ora cobre o
        // original; a reconciliação de conversões atualiza isto depois.
        DB::statement(<<<'SQL'
            UPDATE media m
            SET total_size = COALESCE(
                (SELECT SUM(f.size) FROM media_files f WHERE f.media_id = m.id),
                0
            )
        SQL);
    }

    /**
     * No-op de propósito. Não há como distinguir um `original` criado por este
     * backfill de um criado por upload nativo v3, então apagá-los num rollback
     * isolado destruiria dados de um sistema saudável. Além disso a migration de
     * schema anterior já é irreversível — voltar além deste ponto exige backup.
     */
    public function down(): void
    {
        // intencionalmente vazio
    }
};
