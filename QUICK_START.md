# Quick Start Guide - Laravel Permitted

**Get up and running in 5 minutes!** This guide walks you through the basics step-by-step.

---

## 📋 Table of Contents

1. [Installation](#installation)
2. [Basic Setup (Single-Tenant)](#basic-setup-single-tenant)
3. [Creating Your First Roles & Permissions](#creating-your-first-roles--permissions)
4. [Protecting Routes](#protecting-routes)
5. [Checking Permissions in Controllers](#checking-permissions-in-controllers)
6. [Using Blade Directives](#using-blade-directives)
7. [Next Steps](#next-steps)

---

## Installation

### Step 1: Install via Composer

```bash
composer require williamug/laravel-permitted
```

**What this does:** Downloads the package and its dependencies into your Laravel project.

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=permitted-config
```

**What this does:** Creates `config/permitted.php` where you can customize package behavior.

**Where to find it:** `config/permitted.php` in your Laravel project root.

### Step 3: Run Migrations

```bash
php artisan migrate
```

**What this does:** Creates these database tables:
- `roles` - Stores user roles (e.g., Admin, Editor)
- `permissions` - Stores permissions (e.g., edit posts, delete users)
- `role_user` - Links users to roles
- `permission_role` - Links permissions to roles
- `modules` - (Optional) For organizing permissions
- `sub_modules` - (Optional) For sub-categories

**Don't worry!** If modules are disabled (default), empty tables are fine.

---

## Basic Setup (Single-Tenant)

### Step 1: Check Your Config

Open `config/permitted.php` and verify:

```php
'multi_tenancy' => [
    'enabled' => false,  // ← Should be false for basic apps
],

'modules' => [
    'enabled' => false,  // ← Should be false for simplicity
],
```

**Perfect!** Default settings work for 90% of applications.

### Step 2: Add Traits to User Model

Open `app/Models/User.php` and add:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Williamug\Permitted\Traits\HasRoles;
use Williamug\Permitted\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles, HasPermissions;  // ← Add these two traits

    // ... rest of your User model
}
```

**What this does:** Gives your User model methods like `hasRole()`, `hasPermission()`, `assignRole()`, etc.

**That's it!** Your basic setup is complete. ✅

---

## Creating Your First Roles & Permissions

You can create roles and permissions in three ways:

### Option 1: Tinker (Quick Testing)

```bash
php artisan tinker
```

```php
// Create permissions
$viewPosts = \Williamug\Permitted\Models\Permission::create(['name' => 'view posts']);
$editPosts = \Williamug\Permitted\Models\Permission::create(['name' => 'edit posts']);
$deletePosts = \Williamug\Permitted\Models\Permission::create(['name' => 'delete posts']);

// Create role
$admin = \Williamug\Permitted\Models\Role::create(['name' => 'Admin']);

// Assign permissions to role
$admin->givePermissionTo(['view posts', 'edit posts', 'delete posts']);

// Assign role to user
$user = \App\Models\User::find(1);
$user->assignRole('Admin');

// Test it!
$user->hasPermission('edit posts'); // Returns true
```

### Option 2: Seeder (Recommended for Production)

Create a seeder:

```bash
php artisan make:seeder RolesAndPermissionsSeeder
```

Edit `database/seeders/RolesAndPermissionsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Williamug\Permitted\Models\Role;
use Williamug\Permitted\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            'view posts',
            'create posts',
            'edit posts',
            'delete posts',
            'view users',
            'create users',
            'edit users',
            'delete users',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create Admin Role
        $admin = Role::create([
            'name' => 'Admin',
            'display_name' => 'Administrator',
            'description' => 'Full access to everything',
        ]);
        $admin->givePermissionTo($permissions); // All permissions

        // Create Editor Role
        $editor = Role::create([
            'name' => 'Editor',
            'display_name' => 'Content Editor',
            'description' => 'Can manage posts',
        ]);
        $editor->givePermissionTo(['view posts', 'create posts', 'edit posts']);

        // Create Viewer Role
        $viewer = Role::create([
            'name' => 'Viewer',
            'display_name' => 'Read-Only User',
            'description' => 'Can only view content',
        ]);
        $viewer->givePermissionTo(['view posts', 'view users']);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Update `database/seeders/DatabaseSeeder.php` to auto-run:

```php
public function run(): void
{
    $this->call([
        RolesAndPermissionsSeeder::class,
    ]);
}
```

### Option 3: Migration (For Initial Setup)

Create a migration:

```bash
php artisan make:migration seed_initial_roles_and_permissions
```

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Williamug\Permitted\Models\Role;
use Williamug\Permitted\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $editPosts = Permission::create(['name' => 'edit posts']);
        $admin = Role::create(['name' => 'Admin']);
        $admin->givePermissionTo($editPosts);
    }

    public function down(): void
    {
        Role::where('name', 'Admin')->delete();
        Permission::where('name', 'edit posts')->delete();
    }
};
```

---

## Protecting Routes

### Method 1: Middleware in Routes File

```php
// routes/web.php

use Williamug\Permitted\Middleware\RoleMiddleware;
use Williamug\Permitted\Middleware\PermissionMiddleware;

// Protect single route with role
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:Admin');

// Protect with permission
Route::get('/posts/create', [PostController::class, 'create'])
    ->middleware('permission:create posts');

// Multiple roles (OR logic - user needs ANY of these)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('role:Admin|Editor|Manager');

// Multiple permissions (OR logic)
Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware('permission:delete posts|delete own posts');

// Group protection
Route::middleware(['auth', 'role:Admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('settings', SettingController::class);
});
```

### Method 2: Controller Constructor

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct()
    {
        // Protect all methods
        $this->middleware('permission:view posts');

        // Protect specific methods
        $this->middleware('permission:create posts')->only(['create', 'store']);
        $this->middleware('permission:edit posts')->only(['edit', 'update']);
        $this->middleware('permission:delete posts')->only('destroy');
    }

    public function index()
    {
        // All users with 'view posts' permission can access
    }

    public function create()
    {
        // Only users with 'create posts' permission
    }
}
```

### Method 3: Route Model Binding + Policy

```php
// app/Policies/PostPolicy.php

public function update(User $user, Post $post)
{
    return $user->hasPermission('edit posts') || $post->user_id === $user->id;
}

// Controller
public function update(Request $request, Post $post)
{
    $this->authorize('update', $post);

    // Update post
}
```

---

## Backend Protection Methods

Laravel Permitted provides **9 different ways** to protect your backend controllers. Choose the method that fits your use case.

### 1. Route Middleware (Recommended for Most Cases)

Protect routes before they reach your controller:

```php
// routes/web.php

// Single permission
Route::get('/posts/create', [PostController::class, 'create'])
    ->middleware('permission:create posts');

// Multiple permissions (all required)
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('permission:create posts,publish posts');

// Any permission (user needs at least one)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('permission_or:view dashboard,view reports');

// Role-based
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');

// Combined role or permission
Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware('role_or_permission:admin,delete posts');
```

### 2. Controller Constructor Middleware

Apply middleware to all controller methods or specific ones:

```php
<?php

namespace App\Http\Controllers;

class PostController extends Controller
{
    public function __construct()
    {
        // Apply to all methods
        $this->middleware('permission:manage posts');

        // Apply to specific methods only
        $this->middleware('permission:create posts')->only(['create', 'store']);
        $this->middleware('permission:edit posts')->only(['edit', 'update']);
        $this->middleware('permission:delete posts')->only('destroy');

        // Apply to all methods except
        $this->middleware('permission:view posts')->except('index');
    }
}
```

### 3. Manual Permission Checks

Check permissions directly in controller methods:

```php
public function edit(Post $post)
{
    // Method A: Simple if statement with abort
    if (!auth()->user()->hasPermission('edit posts')) {
        abort(403, 'Unauthorized action.');
    }

    // Method B: Using Laravel's abort_if helper
    abort_if(
        !auth()->user()->hasPermission('edit posts'),
        403,
        'You do not have permission to edit posts.'
    );

    // Method C: Check and redirect (good for web routes)
    if (!auth()->user()->hasPermission('edit posts')) {
        return redirect()->back()
            ->with('error', 'Permission denied.');
    }

    // Method D: Check with flash message
    if (!auth()->user()->can('edit posts')) {
        return redirect()->route('posts.index')
            ->with('error', 'You cannot edit posts.');
    }

    return view('posts.edit', compact('post'));
}
```

### 4. Laravel's authorize() Method

Use Laravel's built-in authorization (requires Gate definition):

```php
// Step 1: Define Gate in App\Providers\AuthServiceProvider

use Williamug\Permitted\Facades\Permitted;

public function boot()
{
    Gate::before(function ($user, $ability) {
        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check permission using Laravel Permitted
        return $user->hasPermission($ability) ? true : null;
    });
}

// Step 2: Use authorize() in controllers

public function update(Request $request, Post $post)
{
    // Will throw AuthorizationException if user lacks permission
    $this->authorize('edit posts');

    $post->update($request->validated());

    return redirect()->route('posts.show', $post);
}

// Step 3: Handle in your exception handler or let Laravel show 403
```

### 5. Form Request Authorization

**Protect your requests at the validation layer:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission
        return $this->user()->hasPermission('create posts');

        // Or check role
        // return $this->user()->hasRole('editor');

        // Or check multiple permissions (all required)
        // return $this->user()->hasAllPermissions(['create posts', 'publish posts']);

        // Or check any permission (at least one)
        // return $this->user()->hasAnyPermission(['create posts', 'create drafts']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'content' => 'required',
        ];
    }

    /**
     * Get the error messages for authorization.
     */
    public function messages(): array
    {
        return [
            'authorize' => 'You do not have permission to create posts.',
        ];
    }
}

// Use in controller
public function store(StorePostRequest $request)
{
    // Authorization already checked in FormRequest
    $post = Post::create($request->validated());

    return redirect()->route('posts.show', $post);
}
```

### 6. Policy-Based Authorization

**For resource-specific permissions (e.g., "edit own posts"):**

```php
// Step 1: Create Policy
// php artisan make:policy PostPolicy --model=Post

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
        // Check both permission AND ownership
        return $user->hasPermission('edit posts')
            && $post->user_id === $user->id;
    }

    /**
     * Determine if user can delete the post
     */
    public function delete(User $user, Post $post): bool
    {
        // Admins can delete any post, authors can delete own posts
        return $user->hasPermission('delete any posts')
            || ($user->hasPermission('delete own posts') && $post->user_id === $user->id);
    }
}

// Step 2: Register Policy in AuthServiceProvider

protected $policies = [
    Post::class => PostPolicy::class,
];

// Step 3: Use in Controller

public function update(Request $request, Post $post)
{
    // Will check PostPolicy@update method
    $this->authorize('update', $post);

    $post->update($request->validated());

    return redirect()->route('posts.show', $post);
}

// Or check manually
public function edit(Post $post)
{
    if (!auth()->user()->can('update', $post)) {
        abort(403, 'You cannot edit this post.');
    }

    return view('posts.edit', compact('post'));
}
```

### 7. API Controller with JSON Responses

**Return JSON for API routes instead of HTML errors:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Check permission and return JSON error
        if (!auth()->user()->hasPermission('create posts')) {
            return response()->json([
                'message' => 'You do not have permission to create posts.',
                'error' => 'Forbidden'
            ], 403);
        }

        $post = Post::create($request->all());

        return response()->json([
            'message' => 'Post created successfully.',
            'data' => $post
        ], 201);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        // Check permission
        if (!auth()->user()->hasPermission('edit posts')) {
            return response()->json([
                'message' => 'Unauthorized.',
                'required_permission' => 'edit posts'
            ], 403);
        }

        $post->update($request->all());

        return response()->json([
            'message' => 'Post updated successfully.',
            'data' => $post
        ]);
    }
}
```

### 8. Super Admin Bypass

**Allow super admins to bypass all permission checks:**

```php
public function edit(Post $post)
{
    // Super admins can do anything
    if (auth()->user()->isSuperAdmin()) {
        return view('posts.edit', compact('post'));
    }

    // Regular users need permission
    if (!auth()->user()->hasPermission('edit posts')) {
        abort(403);
    }

    return view('posts.edit', compact('post'));
}

// Or use the built-in check in HasPermissions trait:
public function destroy(Post $post)
{
    // hasPermission() automatically checks isSuperAdmin() first
    if (!auth()->user()->hasPermission('delete posts')) {
        abort(403);
    }

    $post->delete();

    return redirect()->route('posts.index');
}
```

**Configure super admin in config/permitted.php:**

```php
'super_admin' => [
    'enabled' => true,

    // Option 1: Use callback (most flexible)
    'callback' => function ($user) {
        return $user->email === 'admin@example.com';
    },

    // Option 2: Use Laravel Gate
    'via_gate' => 'is-super-admin',

    // Option 3: Use role name
    'role' => 'super-admin',
],
```

### 9. Ownership + Permission Checks

**Combine permission checks with resource ownership:**

```php
public function update(Request $request, Post $post)
{
    $user = auth()->user();

    // Check if user owns the post OR has edit any permission
    $canEdit = ($post->user_id === $user->id && $user->hasPermission('edit own posts'))
        || $user->hasPermission('edit any posts');

    if (!$canEdit) {
        abort(403, 'You can only edit your own posts.');
    }

    $post->update($request->validated());

    return redirect()->route('posts.show', $post);
}

// Or create a helper method in your controller
protected function authorizeOwnership(User $user, $resource, string $permission): void
{
    if ($resource->user_id !== $user->id && !$user->hasPermission($permission)) {
        abort(403, 'You do not own this resource.');
    }
}

// Use it
public function destroy(Post $post)
{
    $this->authorizeOwnership(auth()->user(), $post, 'delete any posts');

    $post->delete();

    return redirect()->route('posts.index');
}
```

---

## Which Method Should You Use?

| Method | Best For | Pros | Cons |
|--------|----------|------|------|
| **Route Middleware** | Most cases | Clean routes file, DRY, easy to see permissions | Can't check resource ownership |
| **Controller Constructor** | Consistent controller protection | All methods protected in one place | Less flexible than route middleware |
| **Manual Checks** | Complex logic, custom messages | Maximum flexibility, custom responses | More code, easy to forget |
| **authorize()** | Laravel purists | Standard Laravel, works with Policies | Requires Gate setup |
| **Form Requests** | Validation + authorization | Clean controllers, reusable | Extra file per request |
| **Policies** | Resource ownership (edit own posts) | Laravel standard, clean separation | More files, learning curve |
| **API JSON** | API routes | Proper JSON responses | More boilerplate |
| **Super Admin Bypass** | Admin backdoors | Quick admin access | Security risk if misconfigured |
| **Ownership + Permission** | User-owned resources | Fine-grained control | More complex logic |

**Recommendation:** Start with **Route Middleware** for simple permission checks, use **Policies** when you need ownership checks, and use **Form Requests** when combining with validation.

---

## Checking Permissions in Controllers

### Basic Role Checks

```php
public function adminDashboard()
{
    // Check single role
    if (auth()->user()->hasRole('Admin')) {
        return view('admin.dashboard');
    }

    // Check multiple roles (OR - has ANY)
    if (auth()->user()->hasAnyRole(['Admin', 'Super Admin'])) {
        return view('admin.dashboard');
    }

    // Check all roles (AND - has ALL)
    if (auth()->user()->hasAllRoles(['Admin', 'Verified'])) {
        return view('verified-admin.dashboard');
    }

    return abort(403);
}
```

### Super Admin Checks

```php
public function dangerousAction()
{
    // Super admin bypasses all permission checks
    if (auth()->user()->isSuperAdmin()) {
        // Allow anything
        return $this->performAction();
    }

    // Regular permission check
    if (auth()->user()->hasPermission('perform dangerous action')) {
        return $this->performAction();
    }

    abort(403);
}
```

---

## Using Blade Directives

### Show/Hide Content Based on Roles

```blade
{{-- resources/views/dashboard.blade.php --}}

{{-- Check single role --}}
@role('Admin')
    <a href="{{ route('users.index') }}" class="btn btn-primary">
        Manage Users
    </a>
@endrole

{{-- Check multiple roles (has ANY) --}}
@hasanyrole('Admin|Editor|Manager')
    <a href="{{ route('posts.create') }}" class="btn btn-success">
        Create Post
    </a>
@endhasanyrole

{{-- Check all roles (has ALL) --}}
@hasallroles('Admin|Verified')
    <div class="alert alert-success">
        You are a verified administrator
    </div>
@endhasallroles

{{-- Else clause --}}
@role('Admin')
    <p>You are an admin</p>
@else
    <p>You are not an admin</p>
@endrole
```

### Show/Hide Based on Permissions

```blade
{{-- Check single permission --}}
@permission('edit posts')
    <button class="btn btn-primary">Edit Post</button>
@endpermission

{{-- Check multiple permissions (has ANY) --}}
@hasanypermission('edit posts|delete posts')
    <div class="post-actions">
        <button>Manage Post</button>
    </div>
@endhasanypermission

{{-- Check all permissions (has ALL) --}}
@hasallpermissions('edit posts|publish posts')
    <button>Edit & Publish</button>
@endhasallpermissions

{{-- With else --}}
@permission('delete posts')
    <button class="btn btn-danger">Delete</button>
@else
    <span class="text-muted">Cannot delete</span>
@endpermission
```

### Super Admin Directive

```blade
@superadmin
    <div class="admin-panel">
        <a href="{{ route('system.settings') }}">System Settings</a>
        <a href="{{ route('system.logs') }}">View Logs</a>
        <a href="{{ route('system.backup') }}">Backup Database</a>
    </div>
@endsuperadmin
```

### Combining Directives

```blade
<div class="post-actions">
    @permission('view posts')
        <a href="{{ route('posts.show', $post) }}">View</a>
    @endpermission

    @permission('edit posts')
        <a href="{{ route('posts.edit', $post) }}">Edit</a>
    @endpermission

    @permission('delete posts')
        <form action="{{ route('posts.destroy', $post) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit">Delete</button>
        </form>
    @endpermission

    @superadmin
        <a href="{{ route('posts.force-delete', $post) }}" class="text-danger">
            Force Delete
        </a>
    @endsuperadmin
</div>
```

---

## Next Steps

### ✅ You've Completed Basic Setup!

You now know how to:
- Install and configure Laravel Permitted
- Create roles and permissions
- Protect routes with middleware
- Check permissions in controllers
- Use Blade directives

### 🚀 Ready for More?

**Intermediate Topics:**
- [Multi-Tenancy Setup](INSTALLATION.md#multi-tenant-setup) - For SaaS applications
- [Using Modules](README.md#understanding-modules--sub-modules-optional-feature) - Organize 50+ permissions
- [Wildcard Permissions](EXAMPLES.md#example-6-wildcard-permissions) - Grant access to entire sections
- [API Protection](EXAMPLES.md#example-5-api-with-token-based-auth-sanctum) - Secure API endpoints

**Advanced Topics:**
- [Multi-Database Tenancy](EXAMPLES.md#example-3-saas-application-multi-database) - Separate DB per client
- [Sub-Tenants](EXAMPLES.md#example-4-corporate-system-multi-tenant-with-departments) - Departments, Branches
- [Custom Super Admin Logic](README.md#super-admin-bypass-all-permissions) - Email whitelists

**Real-World Examples:**
- Check [EXAMPLES.md](EXAMPLES.md) for 7 complete scenarios

---

## Common Questions

### Q: Do I need to use modules?
**A:** No! Modules are optional (disabled by default). Use them only if you have 50+ permissions.

### Q: Can I use this in a simple blog?
**A:** Absolutely! Default config works perfectly for traditional Laravel apps.

### Q: How do I give a user multiple roles?
**A:** `$user->assignRole(['Admin', 'Editor']);`

### Q: Can a user have permissions from multiple roles?
**A:** Yes! Users get permissions from ALL their assigned roles automatically.

### Q: What if I want to assign permission directly to user (not via role)?
**A:** Currently, permissions must go through roles. This is by design for easier management.

### Q: How do I make someone super admin?
**A:** `$user->assignRole('super admin');` - They'll bypass all permission checks.

### Q: Is it production-ready?
**A:** Yes! Extracted from SaaS applications serving 50+ organizations in production.

---

## Troubleshooting

### Error: "Call to undefined method assignRole()"

**Cause:** You forgot to add traits to User model.

**Fix:**
```php
use Williamug\Permitted\Traits\HasRoles;
use Williamug\Permitted\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles, HasPermissions;  // ← Add these
}
```

### Error: "Table 'roles' doesn't exist"

**Cause:** Migrations haven't been run.

**Fix:**
```bash
php artisan migrate
```

### Permissions Not Working

**Checklist:**
1. Did you run migrations? `php artisan migrate`
2. Did you create roles and permissions? (via seeder/tinker)
3. Did you assign role to user? `$user->assignRole('Admin')`
4. Did role get the permissions? `$role->givePermissionTo('edit posts')`
5. Did you add traits to User model?

**Debug:**
```php
$user = auth()->user();
dd([
    'roles' => $user->roles->pluck('name'),
    'permissions' => $user->getAllPermissions()->pluck('name'),
]);
```

### Cache Issues

**If permissions aren't updating:** Clear permission cache

```php
auth()->user()->forgetCachedPermissions();

// Or in controller
$user->forgetCachedPermissions();
```

---

## Need Help?

- 📖 Read [Full Documentation](README.md)
- 💡 Check [Examples](EXAMPLES.md)
- 🐛 Report issues on GitHub
- 💬 Ask in Laravel communities

**Happy coding!** 🎉
