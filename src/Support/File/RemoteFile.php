<?php

namespace RiseTechApps\Media\Support\File;

use Illuminate\Support\Facades\Storage;

/**
 * Aponta para um arquivo que já vive em um disco.
 *
 * Permite anexar mídia sem trazer o conteúdo para o servidor: a cópia acontece
 * disco a disco (S3 → S3), sem download nem URL assinada intermediária.
 */
class RemoteFile
{
    public function __construct(
        protected string $key,
        protected string $disk,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getName(): string
    {
        return basename($this->key);
    }

    public function getSize(): int
    {
        return (int) Storage::disk($this->disk)->size($this->key);
    }

    public function getMimeType(): ?string
    {
        return Storage::disk($this->disk)->mimeType($this->key) ?: null;
    }
}
