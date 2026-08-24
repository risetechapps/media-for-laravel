<?php

namespace RiseTechApps\Media\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Lançada quando a mídia deixa de existir enquanto um derivado dela é gerado.
 *
 * O caso comum é coleção de arquivo único: o usuário troca a foto, o
 * `FileAdder` remove a anterior em definitivo (`forceDelete`) e o job de
 * conversão da mídia antiga ainda está no meio do caminho. Não é falha —
 * é trabalho que perdeu o sentido. Quem captura deve abortar em silêncio,
 * sem `report()`: o arquivo recém-gravado já foi removido do disco por quem
 * lançou, então nada fica pago no storage.
 */
class MediaNoLongerExists extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("A mídia [{$key}] foi removida durante a geração de um derivado.");
    }
}
