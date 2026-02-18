<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the package should handle multi-tenancy:
    | - 'none': Single-tenant application (no tenant isolation)
    | - 'single_database': Multi-tenant with single database (tenant_id columns)
    | - 'multi_database': Multi-tenant with separate databases per tenant
    |
    */

  'multi_tenancy' => [
    'enabled' => env('PERMITTED_MULTI_TENANCY', false),
    'mode' => env('PERMITTED_TENANCY_MODE', 'single_database'), // none, single_database, multi_database
  ],

  /*
    |--------------------------------------------------------------------------
    | Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Define your tenant model and configuration for multi-tenancy support.
    | This allows the package to automatically scope permissions and roles
    | to specific tenants (facilities, companies, organizations, etc.)
    |
    */

  'tenant' => [
    // Primary tenant model (e.g., Company, Organization, Facility)
    'model' => env('PERMITTED_TENANT_MODEL', 'App\\Models\\Tenant'),
    'foreign_key' => env('PERMITTED_TENANT_KEY', 'tenant_id'),

    // Secondary tenant level (e.g., Branch, Location, Department)
    // Set to null if you only need single-level tenancy
    'sub_tenant' => [
      'enabled' => env('PERMITTED_SUB_TENANT_ENABLED', false),
      'model' => env('PERMITTED_SUB_TENANT_MODEL', null),
      'foreign_key' => env('PERMITTED_SUB_TENANT_KEY', 'sub_tenant_id'),
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    |
    | You can customize the models used by the package. This allows you to
    | extend the base models or use your own implementations.
    |
    */

  'models' => [
    'role' => Williamug\Permitted\Models\Role::class,
    'permission' => Williamug\Permitted\Models\Permission::class,
    'module' => Williamug\Permitted\Models\Module::class,
    'sub_module' => Williamug\Permitted\Models\SubModule::class,
  ],

  /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */

  'table_names' => [
    'roles' => 'roles',
    'permissions' => 'permissions',
    'modules' => 'modules',
    'sub_modules' => 'sub_modules',
    'role_user' => 'role_user',
    'permission_role' => 'permission_role',
  ],

  /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | Customize the column names used in pivot tables.
    |
    */

  'column_names' => [
    'role_pivot_key' => 'role_id',
    'permission_pivot_key' => 'permission_id',
    'user_pivot_key' => 'user_id',
  ],

  /*
    |--------------------------------------------------------------------------
    | Module System (Optional - Recommended for Large Apps)
    |--------------------------------------------------------------------------
    |
    | WHAT ARE MODULES?
    | Modules help organize permissions into logical groups. For example:
    | - "Blog" module contains: create posts, edit posts, delete posts
    | - "Users" module contains: view users, edit users, delete users
    |
    | WHAT ARE SUB-MODULES?
    | Sub-modules provide even more granular organization. For example:
    | - "Academic" module → "Subjects" sub-module → manage subjects permission
    | - "Academic" module → "Classes" sub-module → manage classes permission
    |
    | WHEN TO USE:
    | - ✅ Large applications with 50+ permissions
    | - ✅ Complex systems needing UI grouping (admin panels)
    | - ✅ When you want to grant access to entire modules at once
    |
    | WHEN TO SKIP:
    | - ❌ Simple apps with < 20 permissions (use flat structure)
    | - ❌ You prefer Spatie-style flat permissions
    | - ❌ Third-party integration requiring specific structure
    |
    | Set 'enabled' to false for traditional flat permission structure.
    | Sub-modules are optional even when modules are enabled.
    |
    */

  'modules' => [
    'enabled' => env('PERMITTED_MODULES_ENABLED', false), // Default: false for simplicity
    'require_module' => false, // If true, all permissions MUST belong to a module
    'sub_modules' => [
      'enabled' => env('PERMITTED_SUB_MODULES_ENABLED', false), // Even more optional!
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Super Admin (Bypass All Permission Checks)
    |--------------------------------------------------------------------------
    |
    | WHAT IS SUPER ADMIN?
    | A role that automatically bypasses ALL permission checks. Users with this
    | role have unrestricted access to everything in your application.
    |
    | HOW IT WORKS:
    | When a user has the super admin role:
    | - hasPermission() always returns true
    | - hasRole() checks work normally
    | - Perfect for system administrators
    |
    | SECURITY WARNING:
    | Be VERY careful who you assign this role to! They can do ANYTHING.
    |
    | CUSTOMIZATION OPTIONS:
    | 1. Change 'role_name' to match your preference (e.g., 'Administrator')
    | 2. Use 'via_gate' to define custom logic (e.g., check user email)
    | 3. Set 'enabled' to false if you don't want this feature
    |
    */

  'super_admin' => [
    'enabled' => env('PERMITTED_SUPER_ADMIN_ENABLED', true),

    // The name of the role that has super admin privileges
    'role_name' => env('PERMITTED_SUPER_ADMIN_ROLE', 'super admin'),

    // Optional: Define a custom gate to check super admin status
    // Example: return $user->email === 'admin@example.com';
    // If null, uses role_name check
    'via_gate' => null,

    // Optional: Define a custom callback for super admin check
    // Example: fn($user) => $user->is_system_admin === true
    'callback' => null,
  ],

  /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching of permissions and roles for better performance.
    |
    */

  'cache' => [
    'enabled' => env('PERMITTED_CACHE_ENABLED', true),
    'expiration_time' => env('PERMITTED_CACHE_EXPIRATION', 3600), // seconds
    'key_prefix' => 'permitted',
    'store' => env('PERMITTED_CACHE_STORE', 'default'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Register middleware aliases for easy route protection.
    |
    */

  'middleware' => [
    'role' => 'role',
    'permission' => 'permission',
  ],

  /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The User model that will use the HasRoles and HasPermissions traits.
    |
    */

  'user' => [
    'model' => env('PERMITTED_USER_MODEL', 'App\\Models\\User'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable audit logging for role and permission changes.
    | Requires spatie/laravel-activitylog package.
    |
    */

  'audit_logging' => [
    'enabled' => env('PERMITTED_AUDIT_ENABLED', false),
  ],

  /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Enable wildcard permissions (e.g., 'users.*' grants 'users.create', 'users.edit', etc.)
    |
    */

  'wildcards' => [
    'enabled' => env('PERMITTED_WILDCARDS_ENABLED', false),
  ],

  /*
    |--------------------------------------------------------------------------
    | Teams/Groups Support
    |--------------------------------------------------------------------------
    |
    | Enable team-based permissions where users can have different roles
    | in different teams/groups within the same tenant.
    |
    */

  'teams' => [
    'enabled' => env('PERMITTED_TEAMS_ENABLED', false),
    'model' => env('PERMITTED_TEAM_MODEL', null),
    'foreign_key' => env('PERMITTED_TEAM_KEY', 'team_id'),
  ],
];
