<?php

declare(strict_types=1);

namespace Williamug\Permitted\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!config('permitted.multi_tenancy.enabled')) {
            return;
        }

        if (!Auth::check()) {
            return;
        }

        $tenantKey = config('permitted.tenant.foreign_key', 'tenant_id');
        $user = Auth::user();

        if (method_exists($user, 'getTenantId') && $user->getTenantId()) {
            $builder->where($tenantKey, $user->getTenantId());
        } elseif (isset($user->{$tenantKey})) {
            $builder->where($tenantKey, $user->{$tenantKey});
        }
    }
}
