<?php

declare(strict_types=1);

namespace Williamug\Permitted\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Williamug\Permitted\Scopes\SubTenantScope;
use Williamug\Permitted\Scopes\TenantScope;

class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'display_name', 'description', 'guard_name'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Apply tenant scopes if multi-tenancy is enabled
        if (config('permitted.multi_tenancy.enabled')) {
            static::addGlobalScope(new TenantScope);

            if (config('permitted.tenant.sub_tenant.enabled')) {
                static::addGlobalScope(new SubTenantScope);
            }
        }

        // Auto-populate tenant fields on creation
        static::creating(static function ($model) {
            if (config('permitted.multi_tenancy.enabled') && Auth::check()) {
                $user = Auth::user();
                $tenantKey = config('permitted.tenant.foreign_key');

                if (method_exists($user, 'getTenantId')) {
                    $model->{$tenantKey} = $user->getTenantId();
                } elseif (isset($user->{$tenantKey})) {
                    $model->{$tenantKey} = $user->{$tenantKey};
                }

                if (config('permitted.tenant.sub_tenant.enabled')) {
                    $subTenantKey = config('permitted.tenant.sub_tenant.foreign_key');

                    if (method_exists($user, 'getSubTenantId')) {
                        $model->{$subTenantKey} = $user->getSubTenantId();
                    } elseif (isset($user->{$subTenantKey})) {
                        $model->{$subTenantKey} = $user->{$subTenantKey};
                    }
                }
            }

            // Set default guard name
            if (empty($model->guard_name)) {
                $model->guard_name = config('auth.defaults.guard', 'web');
            }
        });

        // Clear cache on save/delete
        static::saved(static function () {
            static::clearCache();
        });

        static::deleted(static function () {
            static::clearCache();
        });
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('permitted.table_names.roles', parent::getTable());
    }

    /**
     * Relationship: A role has many permissions.
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('permitted.models.permission');

        return $this->belongsToMany(
            $permissionModel,
            config('permitted.table_names.permission_role'),
            config('permitted.column_names.role_pivot_key'),
            config('permitted.column_names.permission_pivot_key')
        )->withTimestamps();
    }

    /**
     * Relationship: A role belongs to many users.
     */
    public function users(): BelongsToMany
    {
        $userModel = config('permitted.user.model');

        return $this->belongsToMany(
            $userModel,
            config('permitted.table_names.role_user'),
            config('permitted.column_names.role_pivot_key'),
            config('permitted.column_names.user_pivot_key')
        )->withTimestamps();
    }

    /**
     * Relationship: A role belongs to a tenant (if multi-tenancy is enabled).
     */
    public function tenant(): ?BelongsTo
    {
        if (! config('permitted.multi_tenancy.enabled')) {
            return null;
        }

        $tenantModel = config('permitted.tenant.model');
        $foreignKey = config('permitted.tenant.foreign_key');

        return $this->belongsTo($tenantModel, $foreignKey);
    }

    /**
     * Relationship: A role belongs to a sub-tenant (if enabled).
     */
    public function subTenant(): ?BelongsTo
    {
        if (! config('permitted.tenant.sub_tenant.enabled')) {
            return null;
        }

        $subTenantModel = config('permitted.tenant.sub_tenant.model');
        $foreignKey = config('permitted.tenant.sub_tenant.foreign_key');

        return $this->belongsTo($subTenantModel, $foreignKey);
    }

    /**
     * Grant permission(s) to the role.
     *
     * @param  string|int|array|\Williamug\Permitted\Models\Permission  $permissions
     * @return $this
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissions = collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                $permissionModel = config('permitted.models.permission');

                return is_numeric($permission)
                    ? $permissionModel::findOrFail($permission)
                    : $permissionModel::where('name', $permission)->firstOrFail();
            })
            ->all();

        $this->permissions()->syncWithoutDetaching($permissions);

        static::clearCache();

        return $this;
    }

    /**
     * Revoke permission(s) from the role.
     *
     * @param  string|int|array|\Williamug\Permitted\Models\Permission  $permissions
     * @return $this
     */
    public function revokePermissionTo(...$permissions): self
    {
        $permissions = collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission->id;
                }

                $permissionModel = config('permitted.models.permission');
                $perm = is_numeric($permission)
                    ? $permissionModel::find($permission)
                    : $permissionModel::where('name', $permission)->first();

                return $perm ? $perm->id : null;
            })
            ->filter()
            ->all();

        $this->permissions()->detach($permissions);

        static::clearCache();

        return $this;
    }

    /**
     * Sync permissions for the role.
     *
     * @return $this
     */
    public function syncPermissions(array $permissions): self
    {
        $this->permissions()->sync($permissions);

        static::clearCache();

        return $this;
    }

    /**
     * Check if role has a specific permission.
     *
     * @param  string|int|\Williamug\Permitted\Models\Permission  $permission
     */
    public function hasPermissionTo($permission): bool
    {
        if ($permission instanceof Permission) {
            $permission = $permission->name;
        } elseif (is_numeric($permission)) {
            $permissionModel = config('permitted.models.permission');
            $perm = $permissionModel::find($permission);
            $permission = $perm ? $perm->name : null;
        }

        if (! $permission) {
            return false;
        }

        return $this->permissions->contains('name', $permission);
    }

    /**
     * Clear the cache for permissions and roles.
     */
    protected static function clearCache(): void
    {
        if (config('permitted.cache.enabled')) {
            $prefix = config('permitted.cache.key_prefix');
            Cache::tags(["{$prefix}_roles", "{$prefix}_permissions"])->flush();
        }
    }

    /**
     * Find a role by name.
     *
     * @return static|null
     */
    public static function findByName(string $name, ?string $guardName = null): ?self
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        return static::where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * Find or create a role by name.
     *
     * @return static
     */
    public static function findOrCreate(string $name, ?string $guardName = null): self
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        $role = static::findByName($name, $guardName);

        if (! $role) {
            $role = static::create([
                'name' => $name,
                'guard_name' => $guardName,
            ]);
        }

        return $role;
    }
}
