<?php

declare(strict_types=1);

namespace Williamug\Permitted\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubModule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['module_id', 'name', 'display_name', 'description', 'icon', 'order'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'module_id' => 'integer',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array<string>
     */
    protected $with = ['permissions'];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('permitted.table_names.sub_modules', parent::getTable());
    }

    /**
     * Relationship: A sub-module belongs to a module.
     */
    public function module(): BelongsTo
    {
        $moduleModel = config('permitted.models.module');

        return $this->belongsTo($moduleModel, 'module_id');
    }

    /**
     * Relationship: A sub-module has many permissions.
     */
    public function permissions(): HasMany
    {
        $permissionModel = config('permitted.models.permission');

        return $this->hasMany($permissionModel, 'sub_module_id');
    }
}
