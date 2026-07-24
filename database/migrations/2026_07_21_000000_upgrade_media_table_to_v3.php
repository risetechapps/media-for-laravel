<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade in-place do schema `media` da era Spatie (v1/v2) para o v3.
 *
 * POR QUE ESTA MIGRATION EXISTE
 * -----------------------------
 * A migration `2024_09_30_170815_create_media_table` foi reescrita mantendo o
 * mesmo nome de arquivo. O migrator identifica migration pelo NOME, não pelo
 * conteúdo — então toda instalação v1/v2 já tem esse arquivo marcado como
 * rodado e o pula, ficando presa no schema Spatie (PK bigint, sem `total_size`,
 * com `generated_conversions`). A `create_media_files` então falha ao criar a FK
 * uuid → bigint. Esta migration, com nome novo, é o que efetivamente roda nessas
 * instalações e converte o schema. Em instalação nova ela é no-op (a `media` já
 * nasce no schema v3).
 *
 * A CHAVE DA PRESERVAÇÃO DE DADOS
 * -------------------------------
 * O PathGenerator antigo usava `{collection}/{media->uuid}` (a coluna `uuid`
 * separada). O novo usa `{collection}/{media->id}`. Promovendo a coluna `uuid`
 * legada a ser a nova PK `id`, os caminhos físicos de original e conversões
 * batem exatamente com os arquivos que já estão no disco — nada precisa ser
 * movido no bucket.
 *
 * Exclusivo do Postgres (o package é pgsql-oriented: GIN, citext, jsonb).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('media')) {
            return;
        }

        // Instalação nova: a `media` já nasceu com PK uuid no schema v3. No-op.
        $idType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns
             WHERE table_name = 'media' AND column_name = 'id'"
        );

        if ($idType && strtolower($idType->data_type) === 'uuid') {
            return;
        }

        // Precisa da coluna `uuid` legada para virar a nova PK. Sem ela não há
        // como preservar os dados com segurança — aborta em vez de corromper.
        if (! Schema::hasColumn('media', 'uuid')) {
            throw new RuntimeException(
                'Upgrade media v3: a tabela `media` está em schema desconhecido '
                . '(sem PK uuid e sem a coluna legada `uuid`). Migração abortada '
                . 'para não corromper dados. Verifique o schema manualmente.'
            );
        }

        // gen_random_uuid() é nativo no PG 13+; a extensão cobre versões antigas.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        DB::transaction(function () {
            // 1. Toda linha precisa de um uuid para virar chave primária.
            DB::statement('UPDATE media SET uuid = gen_random_uuid() WHERE uuid IS NULL');

            // 2. Descarta a PK bigint e o unique legado da coluna uuid.
            //    Sem CASCADE de propósito: se algo referenciar media.id via FK,
            //    isto falha alto em vez de quebrar silenciosamente a integridade.
            DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_pkey');
            DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_uuid_unique');
            DB::statement('ALTER TABLE media DROP COLUMN id');

            // 3. A coluna uuid vira a nova PK `id`.
            DB::statement('ALTER TABLE media RENAME COLUMN uuid TO id');
            DB::statement('ALTER TABLE media ALTER COLUMN id SET NOT NULL');
            DB::statement('ALTER TABLE media ADD PRIMARY KEY (id)');

            // 4. Estado derivado do Spatie sai; a contabilidade de bytes entra.
            DB::statement('ALTER TABLE media DROP COLUMN IF EXISTS generated_conversions');
            DB::statement('ALTER TABLE media ADD COLUMN IF NOT EXISTS total_size bigint NOT NULL DEFAULT 0');

            // 5. json -> jsonb. O índice GIN de escopo (tenancy) exige jsonb.
            DB::statement('ALTER TABLE media ALTER COLUMN manipulations TYPE jsonb USING manipulations::jsonb');
            DB::statement('ALTER TABLE media ALTER COLUMN custom_properties TYPE jsonb USING custom_properties::jsonb');
            DB::statement('ALTER TABLE media ALTER COLUMN responsive_images TYPE jsonb USING responsive_images::jsonb');

            // 6. Índices que o schema v3 espera (order_column já vinha indexado).
            DB::statement('CREATE INDEX IF NOT EXISTS media_collection_name_index ON media (collection_name)');
            DB::statement('CREATE INDEX IF NOT EXISTS media_name_index ON media (name)');
            DB::statement('CREATE INDEX IF NOT EXISTS media_file_name_index ON media (file_name)');
        });
    }

    /**
     * Irreversível: a PK bigint original e a coluna `generated_conversions` não
     * podem ser reconstruídas sem perda. Reverter exigiria restaurar de backup.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'A migração media v1/v2 -> v3 é irreversível (perda da PK bigint e de '
            . 'generated_conversions). Restaure de backup se precisar voltar.'
        );
    }
};
