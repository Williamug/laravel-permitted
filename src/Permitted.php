<?php

declare(strict_types=1);

namespace Williamug\Permitted;

use Illuminate\Support\Collection;
use Williamug\Permitted\Models\Module;
use Williamug\Permitted\Models\Permission;
use Williamug\Permitted\Models\Role;
use Williamug\Permitted\Models\SubModule;

class Permitted
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get the Role model instance.
     */
    public function getRoleModel(): string
    {
        return config('permitted.models.role', Role::class);
    }

    /**
     * Get the Permission model instance.
     */
    public function getPermissionModel(): string
    {
        return config('permitted.models.permission', Permission::class);
    }

    /**
     * Get the Module model instance.
     */
    public function getModuleModel(): string
    {
        return config('permitted.models.module', Module::class);
    }

    /**
     * Get the SubModule model instance.
     */
    public function getSubModuleModel(): string
    {
        return config('permitted.models.sub_module', SubModule::class);
    }

    /**
     * Create a new role.
     */
    public function createRole(string $name, array $attributes = []): Role
    {
        $roleModel = $this->getRoleModel();

        return $roleModel::create(array_merge([
            'name' => $name,
        ], $attributes));
    }

    /**
     * Create a new permission.
     */
    public function createPermission(string $name, array $attributes = []): Permission
    {
        $permissionModel = $this->getPermissionModel();

        return $permissionModel::create(array_merge([
            'name' => $name,
        ], $attributes));
    }

    /**
     * Create a new module.
     */
    public function createModule(string $name, array $attributes = []): Module
    {
        $moduleModel = $this->getModuleModel();

        return $moduleModel::create(array_merge([
            'name' => $name,
        ], $attributes));
    }

    /**
     * Find a role by name.
     */
    public function findRole(string $name, ?string $guardName = null): ?Role
    {
        $roleModel = $this->getRoleModel();

        return $roleModel::findByName($name, $guardName);
    }

    /**
     * Find a permission by name.
     */
    public function findPermission(string $name, ?string $guardName = null): ?Permission
    {
        $permissionModel = $this->getPermissionModel();

        return $permissionModel::findByName($name, $guardName);
    }

    /**
     * Get all roles.
     */
    public function getAllRoles(): Collection
    {
        $roleModel = $this->getRoleModel();

        return $roleModel::all();
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(): Collection
    {
        $permissionModel = $this->getPermissionModel();

        return $permissionModel::all();
    }

    /**
     * Check if multi-tenancy is enabled.
     */
    public function isMultiTenancyEnabled(): bool
    {
        return config('permitted.multi_tenancy.enabled', false);
    }

    /**
     * Check if modules are enabled.
     */
    public function areModulesEnabled(): bool
    {
        return config('permitted.modules.enabled', true);
    }

    /**
     * Sync permissions for a role.
     *
     * @param  \Williamug\Permitted\Models\Role|string  $role
     */
    public function syncPermissions($role, array $permissions): Role
    {
        if (is_string($role)) {
            $role = $this->findRole($role);
        }

        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Assign role to user.
     *
     * @param  mixed  $user
     * @param  string|array  $roles
     * @return mixed
     */
    public function assignRoleToUser($user, $roles)
    {
        if (! is_array($roles)) {
            $roles = [$roles];
        }

        return $user->assignRole(...$roles);
    }
}
