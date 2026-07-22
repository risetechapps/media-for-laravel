<?php

namespace RiseTechApps\Media\Support\Conversions;

use Spatie\Image\Enums\Fit;

/**
 * Definição de uma conversão (miniatura, versão reduzida, etc).
 *
 *   $this->addMediaConversion('thumb')
 *       ->width(368)
 *       ->height(232)
 *       ->format('png')
 *       ->queued();
 */
class Conversion
{
    protected ?int $width = null;

    protected ?int $height = null;

    protected ?Fit $fit = null;

    protected ?string $format = null;

    protected ?int $quality = null;

    protected ?float $sharpen = null;

    // Vazio = transparente: o Spatie não aloca cor de fundo e preserva o alfa
    // ao preencher a folga do Contain. Só aparece em formatos com alfa (png/webp).
    protected string $background = '';

    protected int $pdfPageNumber = 1;

    protected bool $optimize = false;

    protected bool $autoOrient = false;

    protected bool $queued = true;

    /** @var array<int, string> Coleções em que a conversão se aplica; vazio = todas. */
    protected array $collections = [];

    public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    // ------------------------------------------------------------ declaração

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function height(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function fit(Fit $fit, ?int $width = null, ?int $height = null): self
    {
        $this->fit = $fit;
        $this->width = $width ?? $this->width;
        $this->height = $height ?? $this->height;

        return $this;
    }

    public function format(string $format): self
    {
        $this->format = ltrim($format, '.');

        return $this;
    }

    public function quality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function sharpen(float $amount): self
    {
        $this->sharpen = $amount;

        return $this;
    }

    public function background(string $color): self
    {
        $this->background = $color;

        return $this;
    }

    /**
     * Página usada ao gerar miniatura de PDF.
     */
    public function pdfPageNumber(int $page): self
    {
        $this->pdfPageNumber = $page;

        return $this;
    }

    /**
     * Passa o derivado por otimizadores (jpegoptim, pngquant, etc.) antes de
     * salvar. Reduz bytes sem perda visível — bate direto na contabilidade.
     */
    public function optimize(bool $optimize = true): self
    {
        $this->optimize = $optimize;

        return $this;
    }

    /**
     * Corrige a orientação pelo EXIF (foto de celular deitada fica em pé).
     * Aplicado antes do redimensionamento, para as dimensões saírem certas.
     */
    public function orientation(): self
    {
        $this->autoOrient = true;

        return $this;
    }

    public function queued(bool $queued = true): self
    {
        $this->queued = $queued;

        return $this;
    }

    public function nonQueued(): self
    {
        return $this->queued(false);
    }

    /**
     * Restringe a conversão a coleções específicas.
     */
    public function performOnCollections(string ...$collections): self
    {
        $this->collections = $collections;

        return $this;
    }

    // --------------------------------------------------------------- consulta

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getFit(): ?Fit
    {
        return $this->fit;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function getSharpen(): ?float
    {
        return $this->sharpen;
    }

    public function getBackground(): string
    {
        return $this->background;
    }

    public function getPdfPageNumber(): int
    {
        return $this->pdfPageNumber;
    }

    public function shouldOptimize(): bool
    {
        return $this->optimize;
    }

    public function shouldAutoOrient(): bool
    {
        return $this->autoOrient;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    public function appliesToCollection(string $collectionName): bool
    {
        return $this->collections === [] || in_array($collectionName, $this->collections, true);
    }

    /**
     * Extensão do arquivo gerado. Sem formato declarado, mantém a original.
     */
    public function getFormat(?string $fallbackExtension = null): string
    {
        return $this->format ?? $fallbackExtension ?? 'png';
    }

    public function getFileName(string $baseName, ?string $fallbackExtension = null): string
    {
        return "{$baseName}-{$this->name}." . $this->getFormat($fallbackExtension);
    }
}
