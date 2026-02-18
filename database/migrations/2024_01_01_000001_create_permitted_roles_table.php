<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permitted.table_names');
        $multiTenancy = config('permitted.multi_tenancy.enabled');
        $tenantKey = config('permitted.tenant.foreign_key');
        $subTenantEnabled = config('permitted.tenant.sub_tenant.enabled');
        $subTenantKey = config('permitted.tenant.sub_tenant.foreign_key');

        // Create roles table
        Schema::create($tableNames['roles'], function (Blueprint $table) use ($multiTenancy, $tenantKey, $subTenantEnabled, $subTenantKey) {
            $table->id();

            // Add tenant columns if multi-tenancy is enabled
            if ($multiTenancy) {
                $table->unsignedBigInteger($tenantKey)->index();

                if ($subTenantEnabled) {
                    $table->unsignedBigInteger($subTenantKey)->index();
                }
            }

            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->timestamps();

            // Create unique index based on tenant configuration
            if ($multiTenancy) {
                if ($subTenantEnabled) {
                    $table->unique(['name', 'guard_name', $tenantKey, $subTenantKey], 'roles_unique');
                } else {
                    $table->unique(['name', 'guard_name', $tenantKey], 'roles_unique');
                }
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permitted.table_names');
        Schema::dropIfExists($tableNames['roles']);
    }
};
