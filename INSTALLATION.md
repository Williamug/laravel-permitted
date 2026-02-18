# Installation Guide

## Requirements

- PHP >= 8.1
- Laravel >= 10.0
- MySQL/PostgreSQL/SQLite

## Step-by-Step Installation

### 1. Install the Package

```bash
composer require williamug/laravel-permitted
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=permitted-config
```

This creates `config/permitted.php` where you can customize the package.

### 3. Configure Your Setup

Edit `config/permitted.php` based on your needs:

#### For Single-Tenant Application

```php
'multi_tenancy' => [
    'enabled' => false,
],
```

#### For Multi-Tenant (Single Database)

```php
'multi_tenancy' => [
    'enabled' => true,
    'mode' => 'single_database',
],

'tenant' => [
    'model' => App\Models\Company::class,
    'foreign_key' => 'company_id',
    
    'sub_tenant' => [
        'enabled' => true, // For two-level tenancy (Company -> Branch)
        'model' => App\Models\Branch::class,
        'foreign_key' => 'branch_id',
    ],
],
```

### 4. Run Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `roles`
- `permissions`
- `modules` (if modules enabled)
- `sub_modules` (if modules enabled)
- `role_user`
- `permission_role`

### 5. Add Traits to User Model

Edit `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Williamug\Permitted\Traits\HasRoles;
use Williamug\Permitted\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles, HasPermissions;
    
    // For multi-tenancy, add these methods:
    public function getTenantId()
    {
        return $this->company_id; // or your tenant field
    }
    
    public function getSubTenantId()
    {
        return $this->branch_id; // if using sub-tenants
    }
}
```

### 6. (Optional) Customize Table Names

If you need different table names:

```php
// config/permitted.php

'table_names' => [
    'roles' => 'user_roles',
    'permissions' => 'user_permissions',
    'modules' => 'permission_modules',
    'sub_modules' => 'permission_sub_modules',
    'role_user' => 'user_role_assignments',
    'permission_role' => 'role_permission_assignments',
],
```

### 7. Seed Initial Data

Create a seeder:

```bash
php artisan make:seeder RolesAndPermissionsSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Williamug\Permitted\Models\Role;
use Williamug\Permitted\Models\Permission;
use Williamug\Permitted\Models\Module;
use Williamug\Permitted\Models\SubModule;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin role
        $superAdmin = Role::create([
            'name' => 'super admin',
            'display_name' => 'Super Administrator',
            'description' => 'Has access to everything',
        ]);

        // Create modules
        $dashboard = Module::create(['name' => 'Dashboard']);
        $users = Module::create(['name' => 'Users']);
        
        // Create sub-modules
        $userManagement = SubModule::create([
            'module_id' => $users->id,
            'name' => 'User Management',
        ]);
        
        // Create permissions
        $permissions = [
            'view dashboard',
            'view users',
            'create users',
            'edit users',
            'delete users',
        ];
        
        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'module_id' => $users->id,
                'sub_module_id' => $userManagement->id,
            ]);
        }
        
        // Create other roles
        $admin = Role::create(['name' => 'admin']);
        $editor = Role::create(['name' => 'editor']);
        
        // Assign permissions to roles
        $admin->givePermissionTo(['view users', 'create users', 'edit users']);
        $editor->givePermissionTo(['view users', 'edit users']);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### 8. Assign Roles to Users

```php
use App\Models\User;

$user = User::find(1);
$user->assignRole('admin');
```

### 9. Protect Your Routes

In `routes/web.php`:

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
});

Route::middleware(['auth', 'permission:edit users'])->group(function () {
    Route::get('/users/edit', [UserController::class, 'edit']);
});
```

## Troubleshooting

### Tables not created?

Make sure migrations ran successfully:

```bash
php artisan migrate:status
```

### Permissions not working?

1. Clear cache:
```bash
php artisan cache:clear
```

2. Check if user has the trait:
```php
$user = User::find(1);
dd(class_uses_recursive($user));
// Should include HasRoles and HasPermissions
```

3. Refresh permissions:
```php
$user->refreshPermissions();
```

### Multi-tenancy not scoping correctly?

Verify your User model has the tenant methods:

```php
dd($user->getTenantId());
dd($user->getSubTenantId());
```

## Next Steps

- Read the [full documentation](README.md)
- Check [usage examples](EXAMPLES.md)
- Customize the [configuration](config/permitted.php)
