<?php

namespace RiseTechApps\Media\Console\Commands;

use Illuminate\Console\Command;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;

/**
 * Carimba `custom_properties._scope` na mídia que ficou sem escopo.
 *
 * Para que serve
 * --------------
 * O escopo (o "tenancy" desacoplado) só passa a ser gravado depois que um
 * MediaScopeResolver é registrado. Tudo que subiu antes disso está sem `_scope`
 * — e o global scope é fail-closed: no instante em que o resolver entrar, essa
 * mídia some de todas as queries. Este comando fecha essa lacuna antes da
 * virada.
 *
 * Como decidir o escopo
 * ---------------------
 * O contexto de origem NÃO é recuperável do banco: o prefixo de sub-tenant que
 * aparece nas URLs vem do root do disco em tempo de execução, não do `path`
 * gravado em media_files. Por isso o escopo carimbado é o que estiver resolvido
 * agora (ou o passado em --scope), e o comando é feito para rodar uma vez por
 * contexto.
 *
 * Se um mesmo banco guarda mídia de mais de um sub-tenant sem escopo, NÃO rode
 * sem filtro: carimbaria tudo para o contexto atual. Nesse caso restrinja com
 * --model/--collection, ou identifique a mídia de cada sub-tenant listando o
 * prefixo correspondente no storage antes de carimbar.
 *
 * Idempotente: só toca em linha com `_scope` nulo. Não altera timestamps e não
 * escreve nada em disco.
 */
class BackfillMediaScopeCommand extends Command
{
    protected $signature = 'media:backfill-scope
        {--scope=* : Par chave=valor a carimbar (repetível). Sem isto, usa o contexto resolvido agora}
        {--collection= : Limita a uma coleção}
        {--model= : Limita a um model_type (FQCN)}
        {--dry-run : Mostra o que seria carimbado, sem gravar}
        {--force : Não pede confirmação}';

    protected $description = 'Carimba o escopo de tenancy na mídia criada antes do resolver existir.';

    public function handle(): int
    {
        $scope = $this->resolveScope();

        if ($scope === []) {
            $this->error('Nenhum escopo para carimbar.');
            $this->line('Rode dentro de um contexto inicializado ou passe --scope=chave=valor.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Enxerga todos os escopos e a lixeira: mídia soft-deleted continua
        // ocupando disco e precisa do carimbo para a cota enxergá-la depois.
        $query = Media::unscoped()
            ->withTrashed()
            ->whereNull('custom_properties->' . MediaScopeManager::KEY);

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        if ($model = $this->option('model')) {
            $query->where('model_type', $model);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nada a carimbar: nenhuma mídia sem escopo com esses filtros.');

            return self::SUCCESS;
        }

        $this->line('Escopo: ' . json_encode($scope, JSON_UNESCAPED_UNICODE));
        $this->line('Mídias sem escopo: ' . $total);

        if ($dryRun) {
            $this->warn('Modo dry-run: nada será gravado.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Carimbar {$total} mídia(s) com esse escopo?")) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $stamped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($medias) use ($scope, &$stamped, $bar): void {
            foreach ($medias as $media) {
                $properties = $media->custom_properties ?? [];
                $properties[MediaScopeManager::KEY] = $scope;

                $media->custom_properties = $properties;

                // Backfill não é atividade do usuário: preserva updated_at.
                $media->timestamps = false;
                $media->saveQuietly();

                $stamped++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Carimbadas: {$stamped}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveScope(): array
    {
        $pairs = (array) $this->option('scope');

        if ($pairs === []) {
            return app(MediaScopeManager::class)->context();
        }

        $scope = [];

        foreach ($pairs as $pair) {
            if (! str_contains($pair, '=')) {
                $this->warn("Ignorado (esperado chave=valor): {$pair}");

                continue;
            }

            [$key, $value] = explode('=', $pair, 2);

            $scope[$key] = $value;
        }

        return $scope;
    }
}
