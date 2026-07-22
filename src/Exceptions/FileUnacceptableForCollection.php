<?php

namespace RiseTechApps\Media\Exceptions;

use RuntimeException;

class FileUnacceptableForCollection extends RuntimeException
{
    public static function mimeType(?string $mimeType, string $collectionName, array $accepted): self
    {
        $accepted = implode(', ', $accepted);

        return new self(
            "O tipo [{$mimeType}] não é aceito pela coleção [{$collectionName}]. Tipos aceitos: {$accepted}."
        );
    }
}
