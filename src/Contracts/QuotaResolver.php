<?php

namespace RiseTechApps\Media\Contracts;

/**
 * Informa o limite de storage do contexto atual.
 *
 * Usa o mesmo contexto do MediaScopeResolver: o consumidor sabe qual tenant
 * está ativo e devolve o limite do plano dele. O package não conhece planos —
 * só compara o uso do escopo atual contra este número.
 *
 *   class TenancyQuota implements QuotaResolver
 *   {
 *       public function limitInBytes(): ?int
 *       {
 *           return SubTenant::current()?->plan->storage_bytes;
 *       }
 *   }
 */
interface QuotaResolver
{
    /**
     * @return int|null Limite em bytes, ou null para ilimitado.
     */
    public function limitInBytes(): ?int;
}
