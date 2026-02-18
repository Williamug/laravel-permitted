<?php

declare(strict_types=1);

namespace Williamug\Permitted\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SubTenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('permitted.multi_tenancy.enabled')) {
            return;
        }

        if (! config('permitted.tenant.sub_tenant.enabled')) {
            return;
        }

        if (! Auth::check()) {
            return;
        }

        $subTenantKey = config('permitted.tenant.sub_tenant.foreign_key', 'sub_tenant_id');
        $user = Auth::user();

        if (method_exists($user, 'getSubTenantId') && $user->getSubTenantId()) {
            $builder->where($subTenantKey, $user->getSubTenantId());
        } elseif (isset($user->{$subTenantKey})) {
            $builder->where($subTenantKey, $user->{$subTenantKey});
        }
    }
}
