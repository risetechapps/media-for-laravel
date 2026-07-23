<?php

namespace RiseTechApps\Media\Support\Quota;

use Illuminate\Contracts\Container\Container;
use RiseTechApps\Media\Contracts\QuotaResolver;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Models\Scopes\MediaScope;
use RiseTechApps\Media\Support\Reports\Size;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;

/**
 * Cota de storage do contexto atual.
 *
 * Uso = soma de total_size das mídias do escopo atual (mesmo filtro do global
 * scope, incluindo lixeira — que ainda ocupa disco). Limite = o que o
 * QuotaResolver do consumidor informa. Sem resolver de cota, é ilimitado.
 */
class Quota
{
    public function __construct(
        protected Container $container,
        protected MediaScopeManager $scope,
    ) {
    }

    /**
     * Bytes ocupados pelo contexto atual (inclui lixeira).
     */
    public function usage(): int
    {
        // Remove o global scope e reaplica o filtro de contexto explicitamente:
        // não depende de o global scope estar montado, e mede o escopo atual
        // mesmo a partir de contextos de admin.
        $query = Media::withoutGlobalScope(MediaScope::class)->withTrashed();

        return (int) $this->scope->applyTo($query)->sum('total_size');
    }

    /**
     * Limite do contexto atual em bytes, ou null (ilimitado).
     *
     * Prioridade: resolver registrado > limite fixo em config > ilimitado. Um
     * resolver presente vence sempre — inclusive devolvendo null de propósito.
     */
    public function limit(): ?int
    {
        if ($this->container->bound(QuotaResolver::class)) {
            return $this->container->make(QuotaResolver::class)->limitInBytes();
        }

        // Aceita bytes crus ou string legível ('10GB', '500 MB').
        return Size::parse(config('media.quota.default'));
    }

    /**
     * Bytes ainda disponíveis, ou null quando ilimitado.
     */
    public function remaining(): ?int
    {
        $limit = $this->limit();

        return $limit === null ? null : max($limit - $this->usage(), 0);
    }

    public function exceeded(): bool
    {
        $limit = $this->limit();

        return $limit !== null && $this->usage() >= $limit;
    }

    /**
     * Cabe mais $bytes sem estourar a cota? Ilimitado sempre cabe.
     */
    public function canFit(int $bytes): bool
    {
        $limit = $this->limit();

        return $limit === null || ($this->usage() + $bytes) <= $limit;
    }

    /**
     * Percentual usado, ou null quando ilimitado.
     *
     * Por padrão devolve o valor real — pode passar de 100 quando o uso estourou
     * o limite, sinalizando o excesso. Passe $clamp = true para limitar a 100
     * (útil em barra de progresso). exceeded() continua marcando o estouro real.
     */
    public function percentUsed(int $precision = 2, bool $clamp = false): ?float
    {
        $limit = $this->limit();

        if ($limit === null || $limit === 0) {
            return null;
        }

        $percent = $this->usage() / $limit * 100;

        if ($clamp) {
            $percent = min($percent, 100);
        }

        return round($percent, $precision);
    }

    // ------------------------------------------------------------- formatação

    public function usageSize(): Size
    {
        return Size::of($this->usage());
    }

    public function limitSize(): ?Size
    {
        $limit = $this->limit();

        return $limit === null ? null : Size::of($limit);
    }

    public function remainingSize(): ?Size
    {
        $remaining = $this->remaining();

        return $remaining === null ? null : Size::of($remaining);
    }
}
