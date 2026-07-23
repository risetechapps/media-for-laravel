<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índice GIN em custom_properties para o filtro de escopo (tenancy) não virar
 * seq-scan conforme a tabela cresce. Acelera o containment @> usado pelo global
 * scope. Exclusivo do Postgres — outros bancos ignoram sem erro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS media_custom_properties_gin ON media USING gin (custom_properties)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS media_custom_properties_gin');
    }
};
