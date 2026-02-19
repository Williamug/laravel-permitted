<?php

declare(strict_types=1);

namespace Williamug\Permitted\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Williamug\Permitted\Models\Role;

trait HasRoles
{
    /**
     * Relationship: A user belongs to many roles.
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('permitted.models.role');

        return $this->belongsToMany(
            $roleModel,
            config('permitted.table_names.role_user'),
            config('permitted.column_names.user_pivot_key'),
            config('permitted.column_names.role_pivot_key')
        )->withTimestamps();
    }

    /**
     * Assign the given role(s) to the user.
     *
     * @param  string|int|array|\Williamug\Permitted\Models\Role  $roles
     * @return $this
     */
    public function assignRole(...$roles): self
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role;
                }

                $roleModel = config('permitted.models.role');

                return is_numeric($role)
                  ? $roleModel::findOrFail($role)
                  : $roleModel::where('name', $role)->firstOrFail();
            })
            ->all();

        $this->roles()->syncWithoutDetaching($roles);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove the given role(s) from the user.
     *
     * @param  string|int|array|\Williamug\Permitted\Models\Role  $roles
     * @return $this
     */
    public function removeRole(...$roles): self
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role->id;
                }

                $roleModel = config('permitted.models.role');
                $r = is_numeric($role)
                  ? $roleModel::find($role)
                  : $roleModel::where('name', $role)->first();

                return $r ? $r->id : null;
            })
            ->filter()
            ->all();

        $this->roles()->detach($roles);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Sync roles for the user.
     *
     * @return $this
     */
    public function syncRoles(array $roles): self
    {
        $this->roles()->sync($roles);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Determine if the user has the given role.
     *
     * @param  string|int|\Williamug\Permitted\Models\Role  $role
     */
    public function hasRole($role): bool
    {
        if ($role instanceof Role) {
            return $this->roles->contains('id', $role->id);
        }

        if (is_numeric($role)) {
            return $this->roles->contains('id', $role);
        }

        return $this->roles->contains('name', $role);
    }

    /**
     * Determine if the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the user's role names.
     */
    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    /**
     * Check if user is a super admin.
     * Super admins bypass all permission checks.
     */
    public function isSuperAdmin(): bool
    {
        if (! config('permitted.super_admin.enabled', true)) {
            return false;
        }

        // Option 1: Custom callback (highest priority)
        if ($callback = config('permitted.super_admin.callback')) {
            return $callback($this);
        }

        // Option 2: Custom gate
        if ($gate = config('permitted.super_admin.via_gate')) {
            return app(\Illuminate\Contracts\Auth\Access\Gate::class)->allows($gate);
        }

        // Option 3: Role-based check (default)
        $superAdminRole = config('permitted.super_admin.role_name', 'super admin');

        return $this->hasRole($superAdminRole);
    }

    /**
     * Forget the cached permissions for the user.
     */
    protected function forgetCachedPermissions(): void
    {
        if (config('permitted.cache.enabled')) {
            $prefix = config('permitted.cache.key_prefix');
            $key = "{$prefix}_user_{$this->getKey()}_permissions";
            Cache::forget($key);
        }
    }
}
