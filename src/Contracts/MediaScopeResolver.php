<?php

namespace RiseTechApps\Media\Contracts;

/**
 * Resolve o contexto atual usado para particionar a mídia (o "tenant").
 *
 * O package não sabe o que é tenancy: recebe deste resolver um mapa
 * chave→valor, carimba em custom_properties._scope na criação e filtra por ele
 * em toda consulta. Quem sabe o que é sub_tenant/tenant é o consumidor.
 *
 * Devolva [] quando não houver contexto (worker sem contexto, console, request
 * sem tenant). Nesse caso o filtro é fail-closed: só mídia sem escopo aparece.
 *
 *   class TenancyMediaScope implements MediaScopeResolver
 *   {
 *       public function resolve(): array
 *       {
 *           return SubTenant::current()
 *               ? ['sub_tenant_id' => SubTenant::current()->id]
 *               : [];
 *       }
 *   }
 */
interface MediaScopeResolver
{
    /**
     * @return array<string, scalar> Mapa de escopo, ou [] sem contexto.
     */
    public function resolve(): array;
}
