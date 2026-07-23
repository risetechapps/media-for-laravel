<?php

namespace RiseTechApps\Media\Exceptions;

use RuntimeException;

/**
 * Lançada quando anexar um arquivo estouraria a cota do contexto atual.
 * Nada é gravado em disco nem no banco quando isso acontece.
 */
class StorageQuotaExceeded extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $usage,
        public readonly int $attempted,
    ) {
        parent::__construct(
            "Cota de storage excedida: uso {$usage} + {$attempted} bytes ultrapassa o limite de {$limit} bytes."
        );
    }
}
