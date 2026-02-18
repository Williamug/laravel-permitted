<?php

declare(strict_types=1);

namespace Williamug\Permitted\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Williamug\Permitted\Models\Permission;

trait HasPermissions
{
    use HasRoles;

    /**
     * Get all permissions for the user (via roles).
     *
     * @return \Illuminate\Support\Collection
     */
    public function permissions(): Collection
    {
        if (config('permitted.cache.enabled')) {
            $prefix = config('permitted.cache.key_prefix');
            $key = "{$prefix}_user_{$this->getKey()}_permissions";
            $ttl = config('permitted.cache.expiration_time', 3600);

            return Cache::remember($key, $ttl, function () {
                return $this->loadPermissions();
            });
        }

        return $this->loadPermissions();
    }

    /**
     * Load all permissions from user's roles.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function loadPermissions(): Collection
    {
        return $this->roles
            ->map(function ($role) {
                return $role->permissions;
            })
            ->flatten()
            ->unique('id');
    }

    /**
     * Determine if the user has the given permission.
     *
     * @param string|int|\Williamug\Permitted\Models\Permission $permission
     * @return bool
     */
    public function hasPermission($permission): bool
    {
        // Super admin bypasses all permission checks
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($permission instanceof Permission) {
            return $this->permissions()->contains('id', $permission->id);
        }

        if (is_numeric($permission)) {
            return $this->permissions()->contains('id', $permission);
        }

        // Check for wildcard permissions if enabled
        if (config('permitted.wildcards.enabled') && Str::contains($permission, '.')) {
            if ($this->hasWildcardPermission($permission)) {
                return true;
            }
        }

        return $this->permissions()->contains('name', $permission);
    }

    /**
     * Alias for hasPermission.
     *
     * @param string|int|\Williamug\Permitted\Models\Permission $permission
     * @return bool
     */
    public function can($permission): bool
    {
        return $this->hasPermission($permission);
    }

    /**
     * Determine if the user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check for wildcard permission match.
     *
     * @param string $permission
     * @return bool
     */
    protected function hasWildcardPermission(string $permission): bool
    {
        $parts = explode('.', $permission);
        
        // Check for exact wildcard match (e.g., 'users.*')
        $wildcardPermission = $parts[0] . '.*';
        if ($this->permissions()->contains('name', $wildcardPermission)) {
            return true;
        }

        // Check for progressive wildcard matches (e.g., 'users.posts.*' for 'users.posts.edit')
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $wildcard = implode('.', array_slice($parts, 0, $i)) . '.*';
            if ($this->permissions()->contains('name', $wildcard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user's permission names.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPermissionNames(): Collection
    {
        return $this->permissions()->pluck('name');
    }

    /**
     * Check if the user has a permission via a specific role.
     *
     * @param string|int|\Williamug\Permitted\Models\Permission $permission
     * @param string|int|\Williamug\Permitted\Models\Role $role
     * @return bool
     */
    public function hasPermissionViaRole($permission, $role): bool
    {
        if (!$this->hasRole($role)) {
            return false;
        }

        $roleModel = config('permitted.models.role');
        
        if (is_string($role)) {
            $role = $roleModel::where('name', $role)->first();
        } elseif (is_numeric($role)) {
            $role = $roleModel::find($role);
        }

        if (!$role) {
            return false;
        }

        if ($permission instanceof Permission) {
            $permission = $permission->name;
        } elseif (is_numeric($permission)) {
            $permissionModel = config('permitted.models.permission');
            $perm = $permissionModel::find($permission);
            $permission = $perm ? $perm->name : null;
        }

        if (!$permission) {
            return false;
        }

        return $role->permissions->contains('name', $permission);
    }

    /**
     * Get all permissions grouped by role.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPermissionsByRole(): Collection
    {
        return $this->roles->mapWithKeys(function ($role) {
            return [$role->name => $role->permissions->pluck('name')];
        });
    }

    /**
     * Check if user has permission to a specific module.
     *
     * @param string|int $module
     * @return bool
     */
    public function hasModuleAccess($module): bool
    {
        if (!config('permitted.modules.enabled')) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $moduleModel = config('permitted.models.module');
        
        if (is_string($module)) {
            $module = $moduleModel::where('name', $module)->first();
        } elseif (is_numeric($module)) {
            $module = $moduleModel::find($module);
        }

        if (!$module) {
            return false;
        }

        $modulePermissions = $module->getAllPermissions();
        
        foreach ($modulePermissions as $permission) {
            if ($this->hasPermission($permission->name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refresh the user's cached permissions.
     */
    public function refreshPermissions(): void
    {
        $this->forgetCachedPermissions();
        $this->permissions(); // Reload permissions
    }
}
