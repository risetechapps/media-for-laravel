<?php

namespace RiseTechApps\Media\Support\Collections;

use Closure;

/**
 * Definição de uma coleção de mídia.
 *
 * Declarada no model via registerMediaCollections(), descreve as regras que
 * valem para os arquivos daquela coleção: tipos aceitos, disco, se guarda
 * apenas um arquivo, e o que exibir quando está vazia.
 *
 *   $this->addMediaCollection('profile')
 *       ->singleFile()
 *       ->acceptsMimeTypes(['image/jpeg', 'image/png'])
 *       ->useFallbackUrl('/img/sem-foto.png');
 */
class MediaCollection
{
    protected bool $singleFile = false;

    protected array $acceptsMimeTypes = [];

    protected ?Closure $acceptsFile = null;

    protected ?string $diskName = null;

    protected ?string $fallbackUrl = null;

    protected ?string $fallbackPath = null;

    protected bool $generateResponsiveImages = false;

    public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    // ------------------------------------------------------------ declaração

    /**
     * A coleção guarda um único arquivo: ao adicionar um novo, o anterior sai.
     */
    public function singleFile(bool $singleFile = true): self
    {
        $this->singleFile = $singleFile;

        return $this;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     */
    public function acceptsMimeTypes(array $mimeTypes): self
    {
        $this->acceptsMimeTypes = $mimeTypes;

        return $this;
    }

    /**
     * Regra livre de aceitação, avaliada além dos mime types.
     */
    public function acceptsFile(Closure $callback): self
    {
        $this->acceptsFile = $callback;

        return $this;
    }

    public function useDisk(string $diskName): self
    {
        $this->diskName = $diskName;

        return $this;
    }

    /**
     * URL devolvida quando a coleção está vazia.
     */
    public function useFallbackUrl(string $url): self
    {
        $this->fallbackUrl = $url;

        return $this;
    }

    /**
     * Caminho local devolvido quando a coleção está vazia.
     */
    public function useFallbackPath(string $path): self
    {
        $this->fallbackPath = $path;

        return $this;
    }

    public function withResponsiveImages(bool $generate = true): self
    {
        $this->generateResponsiveImages = $generate;

        return $this;
    }

    // --------------------------------------------------------------- consulta

    public function isSingleFile(): bool
    {
        return $this->singleFile;
    }

    public function getAcceptedMimeTypes(): array
    {
        return $this->acceptsMimeTypes;
    }

    public function getDiskName(): ?string
    {
        return $this->diskName;
    }

    public function getFallbackUrl(): ?string
    {
        return $this->fallbackUrl;
    }

    public function getFallbackPath(): ?string
    {
        return $this->fallbackPath;
    }

    public function shouldGenerateResponsiveImages(): bool
    {
        return $this->generateResponsiveImages;
    }

    /**
     * Lista vazia de mime types significa "aceita qualquer tipo".
     */
    public function acceptsMimeType(?string $mimeType): bool
    {
        if ($this->acceptsMimeTypes === []) {
            return true;
        }

        return in_array($mimeType, $this->acceptsMimeTypes, true);
    }

    public function accepts(mixed $file, ?string $mimeType = null): bool
    {
        if (! $this->acceptsMimeType($mimeType)) {
            return false;
        }

        return $this->acceptsFile === null || (bool) ($this->acceptsFile)($file);
    }
}
