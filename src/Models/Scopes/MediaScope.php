<?php

namespace RiseTechApps\Media\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RiseTechApps\Media\Support\Scope\MediaScopeManager;

/**
 * Global scope que particiona a mídia pelo contexto atual.
 *
 * No-op quando nenhum resolver está registrado — o package se comporta como
 * sem tenancy. A lógica de filtro mora no MediaScopeManager, compartilhada com
 * a cota.
 */
class MediaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        app(MediaScopeManager::class)->applyTo($builder);
    }
}
