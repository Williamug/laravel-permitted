<?php

declare(strict_types=1);

namespace Williamug\Permitted\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
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
   * @return array<string, string>
   */
  public function casts(): array
  {
    return [
      'created_at' => 'datetime',
      'updated_at' => 'datetime',
    ];
  }

  /**
   * Boot the model.
   */
  protected static function boot(): void
  {
    parent::boot();

    // Set default guard name on creation
    static::creating(static function ($model) {
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
   *
   * @return string
   */
  public function getTable(): string
  {
    return config('permitted.table_names.permissions', parent::getTable());
  }

  /**
   * Relationship: A permission belongs to many roles.
   */
  public function roles(): BelongsToMany
  {
    $roleModel = config('permitted.models.role');

    return $this->belongsToMany(
      $roleModel,
      config('permitted.table_names.permission_role'),
      config('permitted.column_names.permission_pivot_key'),
      config('permitted.column_names.role_pivot_key')
    )->withTimestamps();
  }

  /**
   * Relationship: A permission belongs to a module (if modules are enabled).
   */
  public function module(): ?BelongsTo
  {
    if (!config('permitted.modules.enabled')) {
      return null;
    }

    $moduleModel = config('permitted.models.module');
    return $this->belongsTo($moduleModel, 'module_id');
  }

  /**
   * Relationship: A permission belongs to a sub-module (if modules are enabled).
   */
  public function subModule(): ?BelongsTo
  {
    if (!config('permitted.modules.enabled')) {
      return null;
    }

    $subModuleModel = config('permitted.models.sub_module');
    return $this->belongsTo($subModuleModel, 'sub_module_id');
  }

  /**
   * Assign this permission to role(s).
   *
   * @param string|int|array|\Williamug\Permitted\Models\Role $roles
   * @return $this
   */
  public function assignToRole(...$roles): self
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

    static::clearCache();

    return $this;
  }

  /**
   * Remove this permission from role(s).
   *
   * @param string|int|array|\Williamug\Permitted\Models\Role $roles
   * @return $this
   */
  public function removeFromRole(...$roles): self
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

    static::clearCache();

    return $this;
  }

  /**
   * Clear the cache for permissions.
   */
  protected static function clearCache(): void
  {
    if (config('permitted.cache.enabled')) {
      $prefix = config('permitted.cache.key_prefix');
      Cache::tags(["{$prefix}_permissions", "{$prefix}_roles"])->flush();
    }
  }

  /**
   * Find a permission by name.
   *
   * @param string $name
   * @param string|null $guardName
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
   * Find or create a permission by name.
   *
   * @param string $name
   * @param string|null $guardName
   * @return static
   */
  public static function findOrCreate(string $name, ?string $guardName = null): self
  {
    $guardName = $guardName ?? config('auth.defaults.guard');

    $permission = static::findByName($name, $guardName);

    if (!$permission) {
      $permission = static::create([
        'name' => $name,
        'guard_name' => $guardName,
      ]);
    }

    return $permission;
  }

  /**
   * Create multiple permissions at once.
   *
   * @param array $permissions
   * @param string|null $guardName
   * @return \Illuminate\Support\Collection
   */
  public static function createMany(array $permissions, ?string $guardName = null): \Illuminate\Support\Collection
  {
    $guardName = $guardName ?? config('auth.defaults.guard');

    return collect($permissions)->map(function ($permission) use ($guardName) {
      if (is_string($permission)) {
        $permission = ['name' => $permission];
      }

      $permission['guard_name'] = $permission['guard_name'] ?? $guardName;

      return static::create($permission);
    });
  }
}
