<?php

namespace RiseTechApps\Media\Support\File;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RiseTechApps\Media\Events\MediaHasBeenAdded;
use RiseTechApps\Media\Exceptions\FileUnacceptableForCollection;
use RiseTechApps\Media\Exceptions\StorageQuotaExceeded;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Collections\MediaCollection;
use RiseTechApps\Media\Support\Disk\MediaDisk;
use RiseTechApps\Media\Support\Filesystem\MediaFilesystem;
use RiseTechApps\Media\Support\Quota\Quota;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;

/**
 * Builder fluente que anexa um arquivo a um model.
 *
 *   $model->addMedia($file)
 *       ->usingFileName('contrato.pdf')
 *       ->withCustomProperties(['origem' => 'importacao'])
 *       ->toMediaCollection('uploads');
 */
class FileAdder
{
    protected Model $subject;

    protected string|UploadedFile|RemoteFile $file;

    protected ?string $name = null;

    protected ?string $fileName = null;

    protected array $customProperties = [];

    protected array $manipulations = [];

    protected bool $preserveOriginal = false;

    protected ?string $conversionsDisk = null;

    protected bool $generateResponsiveImages = false;

    public function __construct(protected MediaFilesystem $filesystem)
    {
    }

    // ------------------------------------------------------------ construção

    public function setSubject(Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function setFile(string|UploadedFile|RemoteFile $file): self
    {
        $this->file = $file;

        return $this;
    }

    // --------------------------------------------------------------- fluência

    public function usingName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function usingFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function withCustomProperties(array $properties): self
    {
        $this->customProperties = $properties;

        return $this;
    }

    public function withProperty(string $key, mixed $value): self
    {
        $this->customProperties[$key] = $value;

        return $this;
    }

    public function withManipulations(array $manipulations): self
    {
        $this->manipulations = $manipulations;

        return $this;
    }

    public function preservingOriginal(bool $preserve = true): self
    {
        $this->preserveOriginal = $preserve;

        return $this;
    }

    public function storingConversionsOnDisk(string $disk): self
    {
        $this->conversionsDisk = $disk;

        return $this;
    }

    public function withResponsiveImages(bool $generate = true): self
    {
        $this->generateResponsiveImages = $generate;

        return $this;
    }

    // ---------------------------------------------------------------- execução

    public function toMediaCollection(string $collectionName = 'default', ?string $disk = null): Media
    {
        $collection = $this->subject->getMediaCollection($collectionName);

        $mimeType = $this->resolveMimeType();

        // Rejeita antes de gravar: um arquivo recusado não pode deixar bytes
        // no disco nem registro no banco.
        if ($collection && ! $collection->accepts($this->file, $mimeType)) {
            throw FileUnacceptableForCollection::mimeType(
                $mimeType,
                $collectionName,
                $collection->getAcceptedMimeTypes()
            );
        }

        // Precedência: disco explícito > disco da coleção > disco padrão.
        $disk ??= $collection?->getDiskName() ?? MediaDisk::name();

        if ($collection?->shouldGenerateResponsiveImages()) {
            $this->generateResponsiveImages = true;
        }

        $size = $this->resolveSize();

        // Cota: barra antes de gravar. Um arquivo que estouraria o limite do
        // contexto atual não pode deixar byte em disco nem registro no banco.
        $quota = app(Quota::class);

        if (! $quota->canFit($size)) {
            throw new StorageQuotaExceeded($quota->limit() ?? 0, $quota->usage(), $size);
        }

        // Carimba o contexto atual (tenancy) em custom_properties._scope. Vazio
        // quando não há contexto — a mídia fica global (sem escopo).
        $customProperties = $this->customProperties;

        if (($scope = app(MediaScopeManager::class)->context()) !== []) {
            $customProperties[MediaScopeManager::KEY] = $scope;
        }

        $fileName = $this->sanitizeFileName($this->fileName ?? $this->resolveOriginalName());

        $media = new Media([
            'collection_name' => $collectionName,
            'name' => $this->name ?? pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'disk' => $disk,
            'conversions_disk' => $this->conversionsDisk,
            'size' => $size,
            'manipulations' => $this->manipulations,
            'custom_properties' => $customProperties,
            // 'pending' sinaliza ao listener que esta mídia pediu variantes
            // responsivas. A geração ainda depende do master switch em config.
            // Após gerar, o campo passa a guardar as larguras produzidas.
            'responsive_images' => $this->generateResponsiveImages ? ['pending' => true] : [],
            'order_column' => $this->nextOrderColumn($collectionName),
        ]);

        $media->model()->associate($this->subject);

        // O registro precisa existir antes da escrita: o caminho em disco é
        // derivado da chave da mídia.
        $media->save();

        try {
            [$source, $sourceDisk] = $this->resolveSource();

            $this->filesystem->storeOriginal($media, $source, $sourceDisk, $this->preserveOriginal);
        } catch (\Throwable $exception) {
            // Mídia sem arquivo é registro fantasma: aparece nas listagens, não
            // abre, e distorce a contagem de storage. Desfaz o registro para que
            // a falha não deixe rastro no banco.
            $media->forceDelete();

            throw $exception;
        }

        // Só depois de o arquivo estar gravado: se a escrita falhasse, o
        // anterior teria sido descartado sem substituto.
        if ($collection?->isSingleFile()) {
            $this->removePreviousMedia($media, $collectionName);
        }

        event(new MediaHasBeenAdded($media->refresh()));

        return $media;
    }

    // ----------------------------------------------------------------- interno

    /**
     * Em coleção de arquivo único, remove o que estava lá antes.
     *
     * Usa exclusão definitiva: o arquivo foi substituído, não arquivado.
     * Mandá-lo para a lixeira faria cada troca de foto dobrar o storage pago
     * até o prune — em coleção que só guarda um item, isso não se justifica.
     */
    protected function removePreviousMedia(Media $media, string $collectionName): void
    {
        $this->subject->media()
            ->inCollection($collectionName)
            ->whereKeyNot($media->getKey())
            ->cursor()
            ->each(fn (Media $previous) => $previous->forceDelete());
    }

    /**
     * @return array{0: string, 1: string|null} caminho e disco de origem (null = local)
     */
    protected function resolveSource(): array
    {
        if ($this->file instanceof RemoteFile) {
            return [$this->file->getKey(), $this->file->getDisk()];
        }

        if ($this->file instanceof UploadedFile) {
            return [$this->file->getRealPath(), null];
        }

        return [$this->file, null];
    }

    protected function resolveOriginalName(): string
    {
        return match (true) {
            $this->file instanceof UploadedFile => $this->file->getClientOriginalName(),
            $this->file instanceof RemoteFile => $this->file->getName(),
            default => basename($this->file),
        };
    }

    protected function resolveMimeType(): ?string
    {
        return match (true) {
            $this->file instanceof UploadedFile => $this->file->getMimeType(),
            $this->file instanceof RemoteFile => $this->file->getMimeType(),
            default => is_file($this->file) ? (mime_content_type($this->file) ?: null) : null,
        };
    }

    protected function resolveSize(): int
    {
        return match (true) {
            $this->file instanceof UploadedFile => (int) $this->file->getSize(),
            $this->file instanceof RemoteFile => $this->file->getSize(),
            default => is_file($this->file) ? (int) filesize($this->file) : 0,
        };
    }

    /**
     * Mantém a extensão e higieniza o nome — o valor vem do cliente e vira caminho
     * em disco, então não pode conter separadores nem sequências de travessia.
     */
    protected function sanitizeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        $name = Str::slug($name) ?: Str::random(16);

        return $extension ? "{$name}.{$extension}" : $name;
    }

    protected function nextOrderColumn(string $collectionName): int
    {
        return (int) Media::query()
            ->where('model_type', $this->subject->getMorphClass())
            ->where('model_id', $this->subject->getKey())
            ->where('collection_name', $collectionName)
            ->max('order_column') + 1;
    }
}
