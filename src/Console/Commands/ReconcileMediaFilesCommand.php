<?php

namespace RiseTechApps\Media\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RiseTechApps\Media\Models\Media;
use Throwable;

/**
 * Reconcilia `media_files` com o que realmente existe no disco.
 *
 * Para que serve
 * --------------
 * O upgrade v1/v2 -> v3 registra apenas o arquivo `original` (o único cujo
 * tamanho está no banco, em `media.size`). Conversões e variantes responsivas
 * herdadas do Spatie ocupam storage mas não têm linha em `media_files` nem
 * entram no `total_size` — o tamanho delas só existe no disco. Este comando
 * varre o diretório de cada mídia, registra cada arquivo físico com o tamanho
 * real e recalcula `total_size`, fechando a contabilidade de bytes.
 *
 * Também serve como ferramenta de reconciliação geral: se algum byte foi escrito
 * fora do MediaFilesystem (o que nunca deveria acontecer), este comando o traz
 * de volta para a contagem.
 *
 * Idempotente: a variante é derivada de forma determinística do caminho, então
 * rodar de novo faz updateOrCreate sobre a mesma linha, sem duplicar. Não move
 * nem apaga nada em disco — só lê tamanhos e escreve no banco.
 */
class ReconcileMediaFilesCommand extends Command
{
    protected $signature = 'media:reconcile
        {--dry-run : Mostra o que seria registrado, sem gravar no banco}
        {--media= : Reconcilia apenas a mídia deste UUID}';

    protected $description = 'Registra em media_files os arquivos físicos herdados (conversões/responsivas) e recalcula total_size.';

    protected int $mediaTouched = 0;
    protected int $filesRegistered = 0;
    protected int $bytesAccounted = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: nada será gravado.');
        }

        // Enxerga todas as mídias, de todos os escopos e inclusive as em lixeira —
        // arquivos de mídia soft-deleted continuam ocupando storage e contam.
        $query = Media::unscoped()->withTrashed();

        if ($id = $this->option('media')) {
            $query->whereKey($id);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nenhuma mídia para reconciliar.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($chunk) use ($dryRun, $bar) {
            foreach ($chunk as $media) {
                try {
                    $this->reconcile($media, $dryRun);
                } catch (Throwable $exception) {
                    $this->newLine();
                    $this->error("Falha na mídia [{$media->getKey()}]: {$exception->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%s%d mídia(s) processada(s), %d arquivo(s) %s, %s contabilizado(s).',
            $dryRun ? '[dry-run] ' : '',
            $this->mediaTouched,
            $this->filesRegistered,
            $dryRun ? 'a registrar' : 'registrado(s)',
            $this->humanBytes($this->bytesAccounted)
        ));

        return self::SUCCESS;
    }

    /**
     * Descobre os arquivos físicos de uma mídia e registra os que faltam.
     */
    protected function reconcile(Media $media, bool $dryRun): void
    {
        $base = trim($media->collection_name, '/') . '/' . $media->getKey();
        $originalStem = pathinfo($media->file_name, PATHINFO_FILENAME);

        // Original e derivados podem estar em discos diferentes (conversions_disk).
        $disks = array_values(array_unique(array_filter([
            $media->disk,
            $media->conversions_disk ?: $media->disk,
        ])));

        // variante => [disk, path, size]
        $discovered = [];

        foreach ($disks as $disk) {
            $storage = Storage::disk($disk);

            foreach ($storage->allFiles($base) as $path) {
                $variant = $this->classify($base, $path, $media->file_name, $originalStem);

                if ($variant === null) {
                    continue;
                }

                // Primeira ocorrência vence — evita que a mesma variante em dois
                // discos seja sobrescrita por uma leitura posterior.
                if (isset($discovered[$variant])) {
                    continue;
                }

                $discovered[$variant] = [
                    'disk' => $disk,
                    'path' => $path,
                    'size' => (int) $storage->size($path),
                ];
            }
        }

        if ($discovered === []) {
            return;
        }

        $this->mediaTouched++;

        foreach ($discovered as $variant => $info) {
            $existing = $media->fileForVariant($variant);

            // Já registrado com o mesmo caminho e tamanho: nada a fazer.
            if ($existing && $existing->path === $info['path'] && (int) $existing->size === $info['size']) {
                continue;
            }

            $this->filesRegistered++;
            $this->bytesAccounted += $info['size'];

            if ($dryRun) {
                continue;
            }

            $media->files()->updateOrCreate(
                ['variant' => $variant],
                ['disk' => $info['disk'], 'path' => $info['path'], 'size' => $info['size']],
            );
        }

        if (! $dryRun) {
            $media->recalculateTotalSize();
        }
    }

    /**
     * Deriva a variante a partir do caminho relativo do arquivo:
     *
     *   {base}/arquivo.jpg                 -> original (quando bate com file_name)
     *   {base}/conversions/x-thumb.webp    -> conversion:thumb
     *   {base}/responsive/responsive-1024.jpg
     *   {base}/responsive-images/...       -> responsive:{width}  (layout Spatie legado)
     *
     * Retorna null para arquivos que não sabemos classificar — melhor ignorar do
     * que inventar uma variante que colidiria com outra.
     */
    protected function classify(string $base, string $path, string $fileName, string $originalStem): ?string
    {
        $relative = ltrim(substr($path, strlen($base)), '/');

        // Arquivo na raiz do diretório da mídia: é o original.
        if (! str_contains($relative, '/')) {
            return $relative === $fileName ? 'original' : null;
        }

        if (str_starts_with($relative, 'conversions/')) {
            $stem = pathinfo($relative, PATHINFO_FILENAME);
            $name = str_starts_with($stem, $originalStem . '-')
                ? substr($stem, strlen($originalStem) + 1)
                : $stem;

            return $name === '' ? null : "conversion:{$name}";
        }

        if (str_starts_with($relative, 'responsive/') || str_starts_with($relative, 'responsive-images/')) {
            $width = $this->parseWidth(pathinfo($relative, PATHINFO_FILENAME));

            if ($width === null) {
                $this->newLine();
                $this->warn("Não consegui extrair a largura de [{$path}] — arquivo responsivo ignorado.");

                return null;
            }

            return "responsive:{$width}";
        }

        return null;
    }

    /**
     * Extrai a largura do nome de uma variante responsiva. Cobre o layout novo
     * (`responsive-1024`) e cai para o primeiro inteiro do nome no legado.
     */
    protected function parseWidth(string $stem): ?int
    {
        if (preg_match('/responsive-(\d+)/', $stem, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/(\d+)/', $stem, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', $value, $units[$unit]);
    }
}
