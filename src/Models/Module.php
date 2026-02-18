<?php

declare(strict_types=1);

namespace Williamug\Permitted\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'display_name', 'description', 'icon', 'order'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<string>
     */
    protected $with = ['subModules'];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('permitted.table_names.modules', parent::getTable());
    }

    /**
     * Relationship: A module has many sub-modules.
     */
    public function subModules(): HasMany
    {
        $subModuleModel = config('permitted.models.sub_module');
        return $this->hasMany($subModuleModel, 'module_id');
    }

    /**
     * Relationship: A module has many permissions.
     */
    public function permissions(): HasMany
    {
        $permissionModel = config('permitted.models.permission');
        return $this->hasMany($permissionModel, 'module_id');
    }

    /**
     * Get all permissions for this module (including sub-module permissions).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return $this->permissions()
            ->get()
            ->merge(
                $this->subModules()
                    ->with('permissions')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
            );
    }
}
