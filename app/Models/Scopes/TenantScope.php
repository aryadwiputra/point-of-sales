<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isBypassed()) {
            return;
        }

        $tenantId = TenantContext::getId();

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
