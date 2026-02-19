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
        $columnNames = config('permitted.column_names');

        // Create role_user pivot table
        Schema::create($tableNames['role_user'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->id();
            $table->foreignId($columnNames['role_pivot_key'])->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->foreignId($columnNames['user_pivot_key'])->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique([$columnNames['role_pivot_key'], $columnNames['user_pivot_key']], 'role_user_unique');
        });

        // Create permission_role pivot table
        Schema::create($tableNames['permission_role'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->id();
            $table->foreignId($columnNames['permission_pivot_key'])->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->foreignId($columnNames['role_pivot_key'])->constrained($tableNames['roles'])->cascadeOnDelete();
            $table->timestamps();

            $table->unique([$columnNames['permission_pivot_key'], $columnNames['role_pivot_key']], 'permission_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permitted.table_names');

        Schema::dropIfExists($tableNames['permission_role']);
        Schema::dropIfExists($tableNames['role_user']);
    }
};
