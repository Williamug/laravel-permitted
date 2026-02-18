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

        if (!$modulesEnabled) {
            return;
        }

        // Create modules table
        Schema::create($tableNames['modules'], function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Create sub_modules table
        Schema::create($tableNames['sub_modules'], function (Blueprint $table) use ($tableNames) {
            $table->id();
            $table->foreignId('module_id')->constrained($tableNames['modules'])->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['module_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permitted.table_names');
        
        if (config('permitted.modules.enabled')) {
            Schema::dropIfExists($tableNames['sub_modules']);
            Schema::dropIfExists($tableNames['modules']);
        }
    }
};
