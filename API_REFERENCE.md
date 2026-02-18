# API Reference - Laravel Permitted

Complete reference for all classes, methods, and configuration options.

---

## Table of Contents

1. [User Trait Methods](#user-trait-methods)
2. [Role Model Methods](#role-model-methods)
3. [Permission Model Methods](#permission-model-methods)
4. [Module Model Methods](#module-model-methods)
5. [Middleware](#middleware)
6. [Blade Directives](#blade-directives)
7. [Laravel Integration](#laravel-integration)
8. [Configuration Reference](#configuration-reference)
9. [Helper Methods](#helper-methods)

---

## User Trait Methods

These methods are available on your User model after adding `HasRoles` and `HasPermissions` traits.

### Role Management

#### `assignRole(...$roles): self`

Assign one or more roles to the user.

```php
// Single role (string)
$user->assignRole('Admin');

// Single role (object)
$admin = Role::findByName('Admin');
$user->assignRole($admin);

// Multiple roles (array)
$user->assignRole(['Admin', 'Editor']);

// Multiple roles (arguments)
$user->assignRole('Admin', 'Editor', 'Manager');

// Returns: User instance (chainable)
```

**Parameters:**
- `$roles` (string|int|Role|array): Role name(s), ID(s), or Role instance(s)

**Returns:** `self` (chainable)

**Example:**
```php
$user->assignRole('Editor')->save();
```

---

#### `removeRole(...$roles): self`

Remove one or more roles from the user.

```php
// Single role
$user->removeRole('Editor');

// Multiple roles
$user->removeRole(['Editor', 'Manager']);

// Remove all roles
$user->syncRoles([]);
```

**Parameters:**
- `$roles` (string|int|Role|array): Role to remove

**Returns:** `self` (chainable)

---

#### `syncRoles($roles): self`

Sync user roles (removes all existing, adds provided).

```php
// Replace all roles with these
$user->syncRoles(['Admin', 'Verified']);

// Remove all roles
$user->syncRoles([]);
```

**Parameters:**
- `$roles` (array): Roles to set (removes others)

**Returns:** `self` (chainable)

**Use Case:** When you want to set exact roles without worrying about existing ones.

---

#### `hasRole(string|array $roles): bool`

Check if user has a specific role.

```php
// Single role
if ($user->hasRole('Admin')) {
    // User is admin
}

// Multiple roles (OR logic - has ANY)
if ($user->hasRole(['Admin', 'Editor'])) {
    // User is admin OR editor
}

// Pipe-separated (same as array)
if ($user->hasRole('Admin|Editor')) {
    // User is admin OR editor
}
```

**Parameters:**
- `$roles` (string|array): Role name or array of role names

**Returns:** `bool` - True if user has ANY of the roles

**Aliases:** `hasAnyRole()`

---

#### `hasAnyRole(array $roles): bool`

Check if user has any of the given roles.

```php
if ($user->hasAnyRole(['Admin', 'Editor', 'Manager'])) {
    // User has at least one of these roles
}
```

**Parameters:**
- `$roles` (array): Array of role names

**Returns:** `bool`

**Note:** Same as `hasRole()` with array

---

#### `hasAllRoles(array $roles): bool`

Check if user has all of the given roles.

```php
if ($user->hasAllRoles(['Admin', 'Verified'])) {
    // User must have BOTH roles
}
```

**Parameters:**
- `$roles` (array): Array of role names

**Returns:** `bool` - True only if user has ALL roles

---

#### `isSuperAdmin(): bool`

Check if user is a super admin.

```php
if ($user->isSuperAdmin()) {
    // This user bypasses all permission checks
}
```

**Returns:** `bool`

**Note:** Super admins automatically pass all permission checks.

**Configuration:** Set in `config/permitted.php`:
```php
'super_admin' => [
    'enabled' => true,
    'role_name' => 'super admin',
    'callback' => fn($user) => $user->email === 'admin@example.com',
]
```

---

### Permission Checking

#### `hasPermission(string|Permission $permission): bool`

Check if user has a specific permission.

```php
if ($user->hasPermission('edit posts')) {
    // User can edit posts
}

// With Permission object
$permission = Permission::findByName('edit posts');
if ($user->hasPermission($permission)) {
    // User can edit posts
}
```

**Parameters:**
- `$permission` (string|int|Permission): Permission name, ID, or instance

**Returns:** `bool`

**Note:**
- Returns true if user is super admin
- Checks permissions from ALL user's roles
- Supports wildcard permissions (if enabled)
- Cached for performance (if caching enabled)

**Aliases:** `can()`

---

#### `can(string $permission): bool`

Laravel-style permission check (alias for `hasPermission`).

```php
if ($user->can('delete posts')) {
    // User can delete posts
}
```

**Parameters:**
- `$permission` (string): Permission name

**Returns:** `bool`

---

#### `hasAnyPermission(array $permissions): bool`

Check if user has any of the given permissions.

```php
if ($user->hasAnyPermission(['edit posts', 'delete posts'])) {
    // User can edit OR delete posts
}
```

**Parameters:**
- `$permissions` (array): Array of permission names

**Returns:** `bool` - True if user has AT LEAST ONE permission

---

#### `hasAllPermissions(array $permissions): bool`

Check if user has all of the given permissions.

```php
if ($user->hasAllPermissions(['edit posts', 'publish posts'])) {
    // User can both edit AND publish
}
```

**Parameters:**
- `$permissions` (array): Array of permission names

**Returns:** `bool` - True only if user has ALL permissions

---

#### `hasPermissionViaRole(string $permission, string $role): bool`

Check if user has permission through a specific role.

```php
if ($user->hasPermissionViaRole('edit posts', 'Editor')) {
    // User has 'edit posts' via 'Editor' role specifically
}
```

**Parameters:**
- `$permission` (string): Permission name
- `$role` (string): Role name

**Returns:** `bool`

**Use Case:** When you need to verify permission came from specific role.

---

### Relationship Methods

#### `roles(): Collection`

Get all roles assigned to the user.

```php
$roles = $user->roles; // Collection of Role models

// Get role names
$roleNames = $user->roles->pluck('name'); // ['Admin', 'Editor']

// Count roles
$count = $user->roles->count();

// Check if collection contains role
if ($user->roles->contains('name', 'Admin')) {
    // User has Admin role
}
```

**Returns:** `Illuminate\Support\Collection` of `Role` models

---

#### `permissions(): Collection`

Get all permissions for the user (from all roles).

```php
$permissions = $user->permissions(); // Collection of Permission models

// Get permission names
$names = $user->permissions()->pluck('name');
// ['edit posts', 'delete posts', 'view users', ...]

// With caching (if enabled in config)
$cached = $user->permissions(); // Uses cache
```

**Returns:** `Illuminate\Support\Collection` of `Permission` models

**Note:** Automatically cached if caching is enabled in config.

---

#### `getAllPermissions(): Collection`

Get all permissions (alias for `permissions()`).

```php
$allPermissions = $user->getAllPermissions();
```

**Returns:** `Collection` of Permission models

---

#### `getRoleNames(): Collection`

Get collection of role names.

```php
$names = $user->getRoleNames(); // Collection: ['Admin', 'Editor']

// Convert to array
$array = $user->getRoleNames()->toArray();

// Check if contains
if ($user->getRoleNames()->contains('Admin')) {
    // Has Admin role
}
```

**Returns:** `Collection` of strings (role names)

---

### Multi-Tenancy Methods

#### `getTenantId(): mixed`

Get the tenant ID for the current user. Override this method in your User model.

```php
class User extends Authenticatable
{
    use HasRoles, HasPermissions;

    public function getTenantId()
    {
        return $this->company_id; // Your tenant column
    }
}
```

**Returns:** `mixed` - Tenant identifier

**Used by:** Automatic tenant scoping

---

#### `getSubTenantId(): mixed`

Get the sub-tenant ID for the current user. Override this method if using sub-tenants.

```php
public function getSubTenantId()
{
    return $this->department_id; // Your sub-tenant column
}
```

**Returns:** `mixed` - Sub-tenant identifier

**Used by:** Automatic sub-tenant scoping

---

### Cache Methods

#### `forgetCachedPermissions(): void`

Clear cached permissions for the user.

```php
// After updating user roles/permissions
$user->syncRoles(['Editor']);
$user->forgetCachedPermissions(); // Clear cache

// Blade/Controller
auth()->user()->forgetCachedPermissions();
```

**Returns:** `void`

**When to use:**
- After changing user roles
- After modifying role permissions
- During testing
- After any permission-related changes

---

## Role Model Methods

### Creating Roles

#### `Role::create(array $attributes): Role`

Create a new role.

```php
use Williamug\Permitted\Models\Role;

$role = Role::create([
    'name' => 'Editor',
    'display_name' => 'Content Editor',  // Optional
    'description' => 'Can edit content', // Optional
    'guard_name' => 'web',              // Optional (default: 'web')
]);
```

**Parameters:**
- `name` (string, required): Role identifier (lowercase, no spaces recommended)
- `display_name` (string, optional): Human-readable name
- `description` (string, optional): Role description
- `guard_name` (string, optional): Auth guard (default: 'web')

**Returns:** `Role` instance

**Note:** Automatically scoped to tenant if multi-tenancy is enabled.

---

#### `Role::findByName(string $name, string $guardName = 'web'): ?Role`

Find role by name.

```php
$admin = Role::findByName('Admin');

if ($admin) {
    // Role exists
}

// With specific guard
$apiAdmin = Role::findByName('Admin', 'api');
```

**Parameters:**
- `$name` (string): Role name
- `$guardName` (string, optional): Guard name (default: 'web')

**Returns:** `Role|null`

---

### Permission Management

#### `givePermissionTo(...$permissions): self`

Give permission(s) to the role.

```php
$role = Role::findByName('Editor');

// Single permission (string)
$role->givePermissionTo('edit posts');

// Multiple permissions (array)
$role->givePermissionTo(['edit posts', 'create posts', 'delete posts']);

// Multiple permissions (arguments)
$role->givePermissionTo('edit posts', 'create posts');

// Permission objects
$permission = Permission::findByName('edit posts');
$role->givePermissionTo($permission);
```

**Parameters:**
- `$permissions` (string|int|Permission|array): Permission(s) to assign

**Returns:** `self` (chainable)

**Note:** Automatically creates permission if it doesn't exist (by name).

---

#### `revokePermissionTo(...$permissions): self`

Remove permission(s) from the role.

```php
$role->revokePermissionTo('delete posts');

// Multiple
$role->revokePermissionTo(['delete posts', 'publish posts']);
```

**Parameters:**
- `$permissions` (string|int|Permission|array): Permission(s) to remove

**Returns:** `self` (chainable)

---

#### `syncPermissions(array $permissions): self`

Sync permissions (removes all existing, adds provided).

```php
// Set exact permissions for role
$role->syncPermissions(['edit posts', 'create posts']);

// Remove all permissions
$role->syncPermissions([]);
```

**Parameters:**
- `$permissions` (array): Permissions to set

**Returns:** `self` (chainable)

---

#### `hasPermissionTo(string|Permission $permission): bool`

Check if role has a specific permission.

```php
if ($role->hasPermissionTo('edit posts')) {
    // Role has this permission
}
```

**Parameters:**
- `$permission` (string|Permission): Permission to check

**Returns:** `bool`

---

### Relationships

#### `permissions(): BelongsToMany`

Get all permissions for the role.

```php
$permissions = $role->permissions; // Collection

// Permission names
$names = $role->permissions->pluck('name');

// Count
$count = $role->permissions->count();
```

**Returns:** `BelongsToMany` relationship / `Collection` when accessed

---

#### `users(): BelongsToMany`

Get all users with this role.

```php
$users = $role->users; // Collection of User models

// Count users
$userCount = $role->users->count();

// Filter users
$activeUsers = $role->users()->where('active', true)->get();
```

**Returns:** `BelongsToMany` relationship

---

## Permission Model Methods

### Creating Permissions

#### `Permission::create(array $attributes): Permission`

Create a new permission.

```php
use Williamug\Permitted\Models\Permission;

$permission = Permission::create([
    'name' => 'edit posts',
    'display_name' => 'Edit Posts',        // Optional
    'description' => 'Can edit blog posts', // Optional
    'guard_name' => 'web',                  // Optional
    'module_id' => 1,                       // Optional (if modules enabled)
    'sub_module_id' => 1,                   // Optional
]);
```

**Parameters:**
- `name` (string, required): Permission identifier
- `display_name` (string, optional): Human-readable name
- `description` (string, optional): What this permission allows
- `guard_name` (string, optional): Auth guard (default: 'web')
- `module_id` (int, optional): Module ID (if using modules)
- `sub_module_id` (int, optional): Sub-module ID

**Returns:** `Permission` instance

---

#### `Permission::createMany(array $names): Collection`

Create multiple permissions at once.

```php
$permissions = Permission::createMany([
    'view posts',
    'create posts',
    'edit posts',
    'delete posts',
]);

// Returns Collection of Permission models
```

**Parameters:**
- `$names` (array): Array of permission names

**Returns:** `Collection` of `Permission` models

---

#### `Permission::findByName(string $name, string $guardName = 'web'): ?Permission`

Find permission by name.

```php
$permission = Permission::findByName('edit posts');

if ($permission) {
    // Permission exists
}
```

**Parameters:**
- `$name` (string): Permission name
- `$guardName` (string, optional): Guard name

**Returns:** `Permission|null`

---

### Role Assignment

#### `assignToRole(...$roles): self`

Assign this permission to role(s).

```php
$permission = Permission::findByName('edit posts');

// Single role
$permission->assignToRole('Editor');

// Multiple roles
$permission->assignToRole(['Admin', 'Editor']);
```

**Parameters:**
- `$roles` (string|Role|array): Role(s) to assign to

**Returns:** `self` (chainable)

---

#### `removeFromRole(...$roles): self`

Remove this permission from role(s).

```php
$permission->removeFromRole('Editor');

// Multiple
$permission->removeFromRole(['Editor', 'Manager']);
```

**Parameters:**
- `$roles` (string|Role|array): Role(s) to remove from

**Returns:** `self` (chainable)

---

### Relationships

#### `roles(): BelongsToMany`

Get all roles that have this permission.

```php
$roles = $permission->roles; // Collection

// Role names
$roleNames = $permission->roles->pluck('name');
```

**Returns:** `BelongsToMany` relationship

---

#### `module(): BelongsTo|null`

Get the module this permission belongs to (if modules enabled).

```php
if ($permission->module) {
    echo $permission->module->name;
}
```

**Returns:** `BelongsTo` relationship or `null` if modules disabled

---

#### `subModule(): BelongsTo|null`

Get the sub-module this permission belongs to.

```php
if ($permission->subModule) {
    echo $permission->subModule->name;
}
```

**Returns:** `BelongsTo` relationship or `null`

---

## Module Model Methods

**Note:** Only available if modules are enabled in config.

### Creating Modules

#### `Module::create(array $attributes): Module`

```php
use Williamug\Permitted\Models\Module;

$module = Module::create([
    'name' => 'User Management',
    'display_name' => 'User Management System',
    'description' => 'Manage users and their access',
    'icon' => 'users', // Optional: for UI
    'order' => 1,      // Optional: for sorting
]);
```

---

### Relationships

#### `permissions(): HasMany`

Get all permissions in this module.

```php
$permissions = $module->permissions;

// Permission names
$names = $module->permissions->pluck('name');
```

---

#### `subModules(): HasMany`

Get all sub-modules in this module.

```php
$subModules = $module->subModules;
```

---

## Middleware

### RoleMiddleware

Protect routes by role.

**Alias:** `role`

**Usage:**
```php
// Single role
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:Admin');

// Multiple roles (OR logic)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('role:Admin|Editor|Manager');
```

**Response:** 403 Forbidden if user doesn't have role

---

### PermissionMiddleware

Protect routes by permission.

**Alias:** `permission`

**Usage:**
```php
// Single permission
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('permission:create posts');

// Multiple permissions (OR logic)
Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware('permission:delete posts|delete own posts');
```

**Response:** 403 Forbidden if user doesn't have permission

---

### RoleOrPermissionMiddleware

Protect by role OR permission (more flexible).

**Alias:** `role_or_permission`

**Usage:**
```php
Route::get('/admin/posts', [AdminPostController::class, 'index'])
    ->middleware('role_or_permission:Admin|edit posts');

// User passes if they:
// - Have 'Admin' role, OR
// - Have 'edit posts' permission
```

---

## Blade Directives

### Role Directives

#### `@role(string $role)`
```blade
@role('Admin')
    <p>You are an admin</p>
@endrole

@role('Admin')
    <p>Admin content</p>
@else
    <p>Not admin</p>
@endrole
```

#### `@hasanyrole(string $roles)`
```blade
@hasanyrole('Admin|Editor|Manager')
    <button>Manage</button>
@endhasanyrole
```

#### `@hasallroles(string $roles)`
```blade
@hasallroles('Admin|Verified')
    <div>Verified Admin</div>
@endhasallroles
```

---

### Permission Directives

#### `@permission(string $permission)`
```blade
@permission('edit posts')
    <button>Edit</button>
@endpermission
```

#### `@hasanypermission(string $permissions)`
```blade
@hasanypermission('edit posts|delete posts')
    <div>Post Actions</div>
@endhasanypermission
```

#### `@hasallpermissions(string $permissions)`
```blade
@hasallpermissions('edit posts|publish posts')
    <button>Edit & Publish</button>
@endhasallpermissions
```

---

### Super Admin Directive

#### `@superadmin`
```blade
@superadmin
    <a href="/system/settings">System Settings</a>
@endsuperadmin
```

---

## Configuration Reference

Complete reference for `config/permitted.php`.

### Multi-Tenancy

```php
'multi_tenancy' => [
    'enabled' => false,  // Enable/disable multi-tenancy
    'mode' => 'single_database', // 'none', 'single_database', 'multi_database'
],
```

### Tenant Configuration

```php
'tenant' => [
    'model' => 'App\\Models\\Tenant',
    'foreign_key' => 'tenant_id',

    'sub_tenant' => [
        'enabled' => false,
        'model' => null,
        'foreign_key' => 'sub_tenant_id',
    ],
],
```

### Models

```php
'models' => [
    'role' => Williamug\Permitted\Models\Role::class,
    'permission' => Williamug\Permitted\Models\Permission::class,
    'module' => Williamug\Permitted\Models\Module::class,
    'sub_module' => Williamug\Permitted\Models\SubModule::class,
],
```

### Table Names

```php
'table_names' => [
    'roles' => 'roles',
    'permissions' => 'permissions',
    'modules' => 'modules',
    'sub_modules' => 'sub_modules',
    'role_user' => 'role_user',
    'permission_role' => 'permission_role',
],
```

### Modules

```php
'modules' => [
    'enabled' => false,
    'require_module' => false,
    'sub_modules' => [
        'enabled' => false,
    ],
],
```

### Super Admin

```php
'super_admin' => [
    'enabled' => true,
    'role_name' => 'super admin',
    'callback' => null,  // Custom function
    'via_gate' => null,  // Custom gate name
],
```

### Cache

```php
'cache' => [
    'enabled' => true,
    'expiration_time' => 3600, // seconds
    'key_prefix' => 'permitted',
    'store' => 'default',
],
```

### Wildcards

```php
'wildcards' => [
    'enabled' => true,
    'character' => '*',
],
```

---

## Laravel Integration

### Using Laravel's authorize() Method

Laravel Permitted integrates seamlessly with Laravel's built-in authorization system through Gates and Policies.

#### Setup Gate Integration

Add this to your `App\Providers\AuthServiceProvider`:

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Your policies here
    ];

    public function boot()
    {
        $this->registerPolicies();

        // Integrate Laravel Permitted with Gates
        Gate::before(function ($user, $ability) {
            // Super admin bypass - they can do anything
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Check if user has permission using Laravel Permitted
            // Return true if has permission, null otherwise
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
```

#### Using authorize() in Controllers

Once configured, you can use Laravel's standard `authorize()` method:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Method 1: authorize() with permission name
     */
    public function create()
    {
        // Will throw AuthorizationException if user lacks permission
        $this->authorize('create posts');

        return view('posts.create');
    }

    /**
     * Method 2: authorize() with Policy method and model
     */
    public function update(Request $request, Post $post)
    {
        // Will check PostPolicy@update if exists, or fall back to Gate
        $this->authorize('update', $post);

        $post->update($request->validated());

        return redirect()->route('posts.show', $post);
    }

    /**
     * Method 3: Manual check with Gate::allows()
     */
    public function destroy(Post $post)
    {
        if (Gate::denies('delete posts')) {
            abort(403, 'You cannot delete posts.');
        }

        $post->delete();

        return redirect()->route('posts.index');
    }

    /**
     * Method 4: Check in view or condition
     */
    public function edit(Post $post)
    {
        $canEdit = Gate::allows('edit posts');

        return view('posts.edit', compact('post', 'canEdit'));
    }
}
```

#### Using with Form Requests

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Uses Laravel Permitted behind the scenes via Gate
        return $this->user()->can('create posts');

        // Or use Laravel Permitted directly
        // return $this->user()->hasPermission('create posts');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'content' => 'required',
        ];
    }
}
```

#### Using with Policies

Policies work seamlessly with Laravel Permitted:

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine if user can view any posts
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view posts');
    }

    /**
     * Determine if user can create posts
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create posts');
    }

    /**
     * Determine if user can update the post
     */
    public function update(User $user, Post $post): bool
    {
        // Combine permission check with ownership
        return $user->hasPermission('edit posts')
            && $post->user_id === $user->id;
    }

    /**
     * Determine if user can delete the post
     */
    public function delete(User $user, Post $post): bool
    {
        // Different permissions for different scenarios
        if ($user->hasPermission('delete any posts')) {
            return true;
        }

        if ($user->hasPermission('delete own posts') && $post->user_id === $user->id) {
            return true;
        }

        return false;
    }
}

// Register in AuthServiceProvider
protected $policies = [
    Post::class => PostPolicy::class,
];

// Use in controller
public function destroy(Post $post)
{
    $this->authorize('delete', $post); // Calls PostPolicy@delete

    $post->delete();

    return redirect()->route('posts.index');
}
```

#### Using @can and @cannot in Blade

Laravel's Blade directives work with Laravel Permitted:

```blade
{{-- Check permission --}}
@can('create posts')
    <a href="{{ route('posts.create') }}">Create Post</a>
@endcan

{{-- Check with model --}}
@can('update', $post)
    <a href="{{ route('posts.edit', $post) }}">Edit</a>
@endcan

@cannot('delete', $post)
    <p>You cannot delete this post.</p>
@endcannot

{{-- Alternative syntax --}}
@if (Gate::allows('edit posts'))
    <button>Edit</button>
@endif
```

#### Error Handling

By default, `authorize()` throws `Illuminate\Auth\Access\AuthorizationException` which Laravel converts to a 403 response.

Customize in `app/Exceptions/Handler.php`:

```php
use Illuminate\Auth\Access\AuthorizationException;

public function render($request, Throwable $exception)
{
    if ($exception instanceof AuthorizationException) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Unauthorized.',
                'error' => 'Forbidden'
            ], 403);
        }

        return redirect()->back()
            ->with('error', 'You do not have permission to perform this action.');
    }

    return parent::render($request, $exception);
}
```

#### Performance Tip

Gate checks are cached automatically by Laravel. For additional performance, enable Laravel Permitted's caching:

```php
// config/permitted.php
'cache' => [
    'enabled' => true,
    'expiration_time' => 3600,
],
```

---

## Configuration Reference

### Facade

```php
use Williamug\Permitted\Facades\Permitted;

// Check if modules are enabled
Permitted::modulesEnabled(); // bool

// Get tenant model
Permitted::getTenantModel(); // string

// Check if caching is enabled
Permitted::cachingEnabled(); // bool
```

---

This API reference covers all public methods and configuration options. For usage examples, see [EXAMPLES.md](EXAMPLES.md).
