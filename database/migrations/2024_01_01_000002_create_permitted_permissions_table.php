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
        $modulesEnabled = config('permitted.modules.enabled');

        // Create permissions table
        Schema::create($tableNames['permissions'], function (Blueprint $table) use ($modulesEnabled, $tableNames) {
            $table->id();
            
            // Add module columns if modules are enabled
            if ($modulesEnabled) {
                $table->foreignId('module_id')->nullable()->constrained($tableNames['modules'])->nullOnDelete();
                $table->foreignId('sub_module_id')->nullable()->constrained($tableNames['sub_modules'])->nullOnDelete();
            }
            
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permitted.table_names');
        Schema::dropIfExists($tableNames['permissions']);
    }
};
