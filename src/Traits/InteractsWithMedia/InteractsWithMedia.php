<?php

namespace RiseTechApps\Media\Traits\InteractsWithMedia;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Collections\MediaCollection;
use RiseTechApps\Media\Support\Conversions\Conversion;
use RiseTechApps\Media\Support\File\FileAdder;
use RiseTechApps\Media\Support\File\RemoteFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Dá ao model a capacidade de possuir mídia.
 *
 *   class Client extends Model implements MediaContract
 *   {
 *       use InteractsWithMedia;
 *   }
 */
trait InteractsWithMedia
{
    protected bool $deletePreservingMedia = false;

    /** @var array<string, MediaCollection> */
    protected array $mediaCollections = [];

    protected bool $mediaCollectionsRegistered = false;

    /** @var array<string, Conversion> */
    protected array $mediaConversions = [];

    public static function bootInteractsWithMedia(): void
    {
        static::deleting(function ($model) {
            if ($model->shouldDeletePreservingMedia()) {
                return;
            }

            // Se o dono usa SoftDeletes, um delete comum apenas o envia para a
            // lixeira — a mídia permanece vinculada e só é removida quando o
            // dono for excluído definitivamente.
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)
                && ! $model->isForceDeleting()) {
                return;
            }

            $model->deleteAllMedia();
        });
    }

    // --------------------------------------------------------------- relação

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    // ------------------------------------------------------------- coleções

    /**
     * Sobrescreva no model para declarar as coleções e suas regras.
     *
     *   public function registerMediaCollections(): void
     *   {
     *       $this->addMediaCollection('profile')
     *           ->singleFile()
     *           ->acceptsMimeTypes(['image/jpeg', 'image/png']);
     *   }
     */
    public function registerMediaCollections(): void
    {
        //
    }

    public function addMediaCollection(string $name): MediaCollection
    {
        $collection = MediaCollection::make($name);

        $this->mediaCollections[$name] = $collection;

        return $collection;
    }

    /**
     * @return array<string, MediaCollection>
     */
    public function getMediaCollections(): array
    {
        if ($this->mediaCollectionsRegistered) {
            return $this->mediaCollections;
        }

        // A flag sobe antes do registro: addMediaCollection() escreve no mesmo
        // array, e sem isso uma consulta feita de dentro de
        // registerMediaCollections() entraria em recursão.
        $this->mediaCollectionsRegistered = true;

        $this->registerMediaCollections();

        return $this->mediaCollections;
    }

    public function getMediaCollection(string $name): ?MediaCollection
    {
        return $this->getMediaCollections()[$name] ?? null;
    }

    // ------------------------------------------------------------- conversões

    /**
     * Sobrescreva no model para declarar as conversões.
     *
     *   public function registerMediaConversions(?Media $media = null): void
     *   {
     *       $this->addMediaConversion('thumb')
     *           ->width(368)->height(232)
     *           ->format('png')
     *           ->queued();
     *   }
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        //
    }

    public function addMediaConversion(string $name): Conversion
    {
        $conversion = Conversion::make($name);

        $this->mediaConversions[$name] = $conversion;

        return $conversion;
    }

    /**
     * As definições são reconstruídas a cada chamada porque podem depender da
     * mídia recebida (tamanhos diferentes conforme o arquivo, por exemplo).
     *
     * @return array<string, Conversion>
     */
    public function getMediaConversions(?Media $media = null): array
    {
        $this->mediaConversions = [];

        $this->registerMediaConversions($media);

        return $this->mediaConversions;
    }

    // ------------------------------------------------------------- adicionar

    public function addMedia(string|UploadedFile|RemoteFile $file): FileAdder
    {
        return app(FileAdder::class)
            ->setSubject($this)
            ->setFile($file);
    }

    public function addMediaFromRequest(string $key): FileAdder
    {
        $file = request()->file($key);

        if (! $file instanceof UploadedFile) {
            throw new \InvalidArgumentException("A requisição não contém o arquivo [{$key}].");
        }

        return $this->addMedia($file);
    }

    /**
     * Anexa um arquivo que já está em um disco.
     *
     * A cópia acontece disco a disco — sem baixar o conteúdo para o servidor
     * nem depender de URL assinada intermediária.
     */
    public function addMediaFromDisk(string $key, ?string $disk = null): FileAdder
    {
        return $this->addMedia(new RemoteFile($key, $disk ?? config('filesystems.default')));
    }

    /**
     * Baixa um arquivo de uma URL e o anexa.
     *
     * O download é feito em stream para um arquivo temporário — o conteúdo não
     * passa inteiro pela memória. O temporário é removido automaticamente ao
     * mover para o disco de destino.
     *
     * ATENÇÃO: a URL é seguida como informada. Não passe endereço vindo
     * diretamente do usuário final sem validar o destino, sob risco de SSRF
     * (acesso a serviços internos ou a endpoints de metadados da infra).
     * Prefira `addMediaFromDisk()` quando o arquivo já estiver no seu storage.
     */
    public function addMediaFromUrl(string $url, ?string $fileName = null): FileAdder
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("URL inválida [{$url}].");
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'media-');

        $response = Http::timeout(config('media.download.timeout', 30))
            ->sink($temporaryFile)
            ->get($url);

        if ($response->failed()) {
            @unlink($temporaryFile);

            throw new \RuntimeException("Não foi possível baixar o arquivo de [{$url}].");
        }

        return $this->addMedia($temporaryFile)
            ->usingFileName($fileName ?? $this->fileNameFromUrl($url, $response->header('Content-Type')));
    }

    /**
     * Deriva o nome do arquivo a partir da URL; sem extensão utilizável,
     * recorre ao Content-Type devolvido pelo servidor.
     */
    protected function fileNameFromUrl(string $url, ?string $contentType = null): string
    {
        $name = basename((string) parse_url($url, PHP_URL_PATH));

        if ($name !== '' && pathinfo($name, PATHINFO_EXTENSION) !== '') {
            return $name;
        }

        $base = $name !== '' ? pathinfo($name, PATHINFO_FILENAME) : Str::random(16);

        $mime = trim(explode(';', (string) $contentType)[0]);
        $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? null;

        return $extension ? "{$base}.{$extension}" : $base;
    }

    // -------------------------------------------------------------------- ler

    public function getMedia(string $collectionName = 'default'): Collection
    {
        return $this->media()
            ->inCollection($collectionName)
            ->ordered()
            ->get();
    }

    public function getFirstMedia(string $collectionName = 'default'): ?Media
    {
        return $this->media()
            ->inCollection($collectionName)
            ->ordered()
            ->first();
    }

    public function hasMedia(string $collectionName = ''): bool
    {
        return $this->media()
            ->when($collectionName !== '', fn ($query) => $query->inCollection($collectionName))
            ->exists();
    }

    /**
     * URL da primeira mídia da coleção; vazia, devolve o fallback declarado.
     */
    public function getFirstMediaUrl(string $collectionName = 'default', ?string $conversionName = null): string
    {
        $media = $this->getFirstMedia($collectionName);

        if ($media) {
            return $media->getFullUrl($conversionName);
        }

        return $this->getMediaCollection($collectionName)?->getFallbackUrl() ?? '';
    }

    public function getFirstMediaPath(string $collectionName = 'default', ?string $conversionName = null): ?string
    {
        $media = $this->getFirstMedia($collectionName);

        if ($media) {
            return $media->getPathRelativeToRoot($conversionName);
        }

        return $this->getMediaCollection($collectionName)?->getFallbackPath();
    }

    // ---------------------------------------------------------------- remover

    public function clearMediaCollection(string $collectionName = 'default'): static
    {
        $this->media()
            ->inCollection($collectionName)
            ->cursor()
            ->each(fn (Media $media) => $media->delete());

        return $this;
    }

    /**
     * @param  array<int, Media|string>|Collection  $excludedMedia
     */
    public function clearMediaCollectionExcept(string $collectionName = 'default', array|Collection $excludedMedia = []): static
    {
        $keep = collect($excludedMedia)
            ->map(fn ($media) => $media instanceof Media ? $media->getKey() : $media)
            ->all();

        $this->media()
            ->inCollection($collectionName)
            ->whereNotIn('id', $keep)
            ->cursor()
            ->each(fn (Media $media) => $media->delete());

        return $this;
    }

    public function deleteAllMedia(): static
    {
        $this->media()
            ->cursor()
            ->each(fn (Media $media) => $media->delete());

        return $this;
    }

    // -------------------------------------------------------- preservar mídia

    public function deletePreservingMedia(): static
    {
        $this->deletePreservingMedia = true;

        $this->delete();

        return $this;
    }

    public function shouldDeletePreservingMedia(): bool
    {
        return $this->deletePreservingMedia;
    }
}
