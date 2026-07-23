<?php

namespace RiseTechApps\Media\Support\Scope;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use RiseTechApps\Media\Contracts\MediaScopeResolver;

/**
 * Ponto central do escopo de mídia (o "tenancy" desacoplado).
 *
 * Guarda o resolver de contexto — registrado por config ou em runtime — e
 * concentra a lógica de filtro, reaproveitada pelo global scope e pela cota,
 * para as duas nunca divergirem.
 *
 * Chave reservada dentro de custom_properties onde o contexto é carimbado.
 */
class MediaScopeManager
{
    public const KEY = '_scope';

    /** @var (callable(): array)|null Resolver definido em runtime, tem prioridade. */
    protected $runtimeResolver = null;

    public function __construct(protected Container $container)
    {
    }

    /**
     * Define o resolver em runtime (sobrepõe o de config).
     *
     *   Media::resolveScopeUsing(fn () => ['sub_tenant_id' => 42]);
     */
    public function resolveUsing(callable $resolver): void
    {
        $this->runtimeResolver = $resolver;
    }

    /**
     * Há resolver ativo? Sem resolver, o package roda sem particionar nada —
     * o global scope vira no-op e nada é carimbado.
     */
    public function enabled(): bool
    {
        return $this->runtimeResolver !== null
            || $this->container->bound(MediaScopeResolver::class);
    }

    /**
     * Contexto atual: mapa chave→valor, ou [] quando não há contexto.
     *
     * @return array<string, scalar>
     */
    public function context(): array
    {
        if ($this->runtimeResolver !== null) {
            return (array) ($this->runtimeResolver)();
        }

        if ($this->container->bound(MediaScopeResolver::class)) {
            return $this->container->make(MediaScopeResolver::class)->resolve();
        }

        return [];
    }

    /**
     * Aplica o filtro do contexto atual a uma query.
     *
     * - Sem resolver: não toca na query (package sem tenancy).
     * - Com resolver e contexto vazio: fail-closed — só mídia sem escopo.
     * - Com resolver e contexto presente: só mídia carimbada com esse contexto.
     */
    public function applyTo(Builder $query): Builder
    {
        if (! $this->enabled()) {
            return $query;
        }

        $context = $this->context();

        if ($context === []) {
            return $query->whereNull('custom_properties->' . self::KEY);
        }

        // Containment top-level (custom_properties @> {"_scope": {...}}) para
        // aproveitar o índice GIN em custom_properties.
        return $query->whereJsonContains('custom_properties', [self::KEY => $context]);
    }
}
