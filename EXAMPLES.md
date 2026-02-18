# Usage Examples

This guide demonstrates **Laravel Permitted** in various real-world scenarios, from simple single-tenant apps to complex multi-tenant architectures.

---

## Example 1: Simple Blog (Single-Tenant)

Perfect for traditional Laravel applications without multi-tenancy.

### Configuration

```php
// config/permitted.php

'multi_tenancy' => [
    'enabled' => false,  // No tenant isolation
],
```

### User Model

```php
use Williamug\Permitted\Traits\HasRoles;
use Williamug\Permitted\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles, HasPermissions;
}
```

### Seeding Permissions

```php
use Williamug\Permitted\Models\Role;
use Williamug\Permitted\Models\Permission;
use Williamug\Permitted\Models\Module;

class BlogPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create Modules
        $content = Module::create(['name' => 'Content Management']);
        $users = Module::create(['name' => 'User Management']);

        // Create Permissions
        $viewPosts = Permission::create([
            'name' => 'view posts',
            'module_id' => $content->id,
        ]);

        $createPosts = Permission::create([
            'name' => 'create posts',
            'module_id' => $content->id,
        ]);

        $editPosts = Permission::create([
            'name' => 'edit posts',
            'module_id' => $content->id,
        ]);

        $deletePosts = Permission::create([
            'name' => 'delete posts',
            'module_id' => $content->id,
        ]);

        $manageUsers = Permission::create([
            'name' => 'manage users',
            'module_id' => $users->id,
        ]);

        // Create Roles
        $admin = Role::create(['name' => 'Admin']);
        $admin->givePermissionTo(['view posts', 'create posts', 'edit posts', 'delete posts', 'manage users']);

        $editor = Role::create(['name' => 'Editor']);
        $editor->givePermissionTo(['view posts', 'create posts', 'edit posts']);

        $author = Role::create(['name' => 'Author']);
        $author->givePermissionTo(['view posts', 'create posts']);

        $subscriber = Role::create(['name' => 'Subscriber']);
        $subscriber->givePermissionTo(['view posts']);
    }
}
```

### Controller Usage

```php
class PostController extends Controller
{
    public function index()
    {
        // Check permission
        if (!auth()->user()->hasPermission('view posts')) {
            abort(403);
        }

        return view('posts.index');
    }

    public function create()
    {
        $this->authorize('create posts'); // Using Laravel policy-style

        return view('posts.create');
    }

    public function store(Request $request)
    {
        if (auth()->user()->hasRole('Author|Editor|Admin')) {
            // Create post
        }
    }
}
```

### Route Protection

```php
// routes/web.php

use Williamug\Permitted\Middleware\RoleMiddleware;
use Williamug\Permitted\Middleware\PermissionMiddleware;

Route::middleware(['auth'])->group(function () {

    // Role-based routes
    Route::middleware(['role:Admin'])->group(function () {
        Route::resource('users', UserController::class);
    });

    // Permission-based routes
    Route::middleware(['permission:create posts'])->group(function () {
        Route::get('/posts/create', [PostController::class, 'create']);
        Route::post('/posts', [PostController::class, 'store']);
    });

    // Multiple roles
    Route::middleware(['role:Admin|Editor'])->group(function () {
        Route::put('/posts/{post}', [PostController::class, 'update']);
    });
});
```

### Blade Directives

```blade
@role('Admin')
    <a href="{{ route('users.index') }}">Manage Users</a>
@endrole

@permission('create posts')
    <a href="{{ route('posts.create') }}">New Post</a>
@endpermission

@hasanyrole('Admin|Editor')
    <button>Edit</button>
@endhasanyrole

@superadmin
    <a href="/admin/settings">System Settings</a>
@endsuperadmin
```

---

## Example 2: E-commerce Platform (Multi-Tenant, Single Database)

Multiple stores sharing one database with automatic data isolation.

### Configuration

```php
// config/permitted.php

'multi_tenancy' => [
    'enabled' => true,
    'mode' => 'single_database',
],

'tenant' => [
    'model' => App\Models\Store::class,
    'foreign_key' => 'store_id',
    'sub_tenant' => [
        'enabled' => false,
    ],
],
```

### Models

```php
// Store Model
class Store extends Model
{
    protected $fillable = ['name', 'domain', 'status'];
}

// User Model
class User extends Authenticatable
{
    use HasRoles, HasPermissions;

    public function getTenantId()
    {
        return $this->store_id;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
```

### Seeding Per Store

```php
class EcommercePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            // Create a temporary user for this store to set tenant context
            $admin = User::create([
                'name' => 'Admin',
                'email' => "admin@{$store->domain}",
                'store_id' => $store->id,
                'password' => bcrypt('password'),
            ]);

            auth()->login($admin);

            // Create Modules
            $products = Module::create(['name' => 'Products']);
            $orders = Module::create(['name' => 'Orders']);
            $customers = Module::create(['name' => 'Customers']);

            // Create Permissions (automatically scoped to store)
            Permission::create(['name' => 'manage products', 'module_id' => $products->id]);
            Permission::create(['name' => 'view orders', 'module_id' => $orders->id]);
            Permission::create(['name' => 'process orders', 'module_id' => $orders->id]);
            Permission::create(['name' => 'manage customers', 'module_id' => $customers->id]);

            // Create Roles (automatically scoped to store)
            $storeOwner = Role::create(['name' => 'Store Owner']);
            $storeOwner->givePermissionTo(['manage products', 'view orders', 'process orders', 'manage customers']);

            $storeManager = Role::create(['name' => 'Manager']);
            $storeManager->givePermissionTo(['view orders', 'process orders']);

            $salesPerson = Role::create(['name' => 'Sales Person']);
            $salesPerson->givePermissionTo(['view orders']);

            // Assign role to admin
            $admin->assignRole('Store Owner');

            auth()->logout();
        }
    }
}
```

### Middleware for Tenant Resolution

```php
// app/Http/Middleware/SetStoreTenant.php

class SetStoreTenant
{
    public function handle($request, Closure $next)
    {
        $domain = $request->getHost();
        $store = Store::where('domain', $domain)->firstOrFail();

        // Store tenant in session or set globally
        config(['permitted.current_tenant_id' => $store->id]);

        return $next($request);
    }
}
```

---

## Example 3: SaaS Application (Multi-Database)

Each organization gets their own database.

### Configuration

```php
// config/permitted.php

'multi_tenancy' => [
    'enabled' => true,
    'mode' => 'multi_database',
],

'tenant' => [
    'model' => App\Models\Organization::class,
    'foreign_key' => 'organization_id',
    'database_connection' => 'tenant', // Connection name for tenant databases
],
```

### Database Configuration

```php
// config/database.php

'connections' => [
    'tenant' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'database' => null, // Will be set dynamically
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
    ],
],
```

### Tenant Switching Middleware

```php
class SwitchTenantDatabase
{
    public function handle($request, Closure $next)
    {
        $subdomain = explode('.', $request->getHost())[0];
        $org = Organization::where('subdomain', $subdomain)->firstOrFail();

        // Switch database connection
        config(['database.connections.tenant.database' => $org->database_name]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Set default connection
        DB::setDefaultConnection('tenant');

        return $next($request);
    }
}
```

### Models

```php
class User extends Authenticatable
{
    use HasRoles, HasPermissions;

    protected $connection = 'tenant'; // Use tenant database
}

// Roles and Permissions automatically use tenant connection
```

---

## Example 4: Corporate System (Multi-Tenant with Departments)

Companies with multiple departments (sub-tenant support).

### Configuration

```php
// config/permitted.php

'multi_tenancy' => [
    'enabled' => true,
    'mode' => 'single_database',
],

'tenant' => [
    'model' => App\Models\Company::class,
    'foreign_key' => 'company_id',

    'sub_tenant' => [
        'enabled' => true,
        'model' => App\Models\Department::class,
        'foreign_key' => 'department_id',
    ],
],
```

### User Model

```php
class User extends Authenticatable
{
    use HasRoles, HasPermissions;

    public function getTenantId()
    {
        return $this->company_id;
    }

    public function getSubTenantId()
    {
        return $this->department_id;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
```

### Usage

```php
// Roles and permissions are scoped to company AND department
$hr = Department::where('name', 'HR')->first();
$it = Department::where('name', 'IT')->first();

// Create HR Manager role for HR department
auth()->login($hrUser); // User in HR department
$hrManager = Role::create(['name' => 'HR Manager']);
$hrManager->givePermissionTo(['view employees', 'hire employees']);

// Create IT Support role for IT department
auth()->login($itUser); // User in IT department
$itSupport = Role::create(['name' => 'IT Support']);
$itSupport->givePermissionTo(['manage systems', 'help desk']);

// Users in HR can't see IT roles and vice versa!
```

---

## Example 5: API with Token-Based Auth (Sanctum)

Secure API endpoints with role and permission checks.

### Configuration

```php
// config/permitted.php (same as Example 1 - single tenant)

'multi_tenancy' => [
    'enabled' => false,
],
```

### API Routes

```php
// routes/api.php

use Williamug\Permitted\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum'])->group(function () {

    // Public endpoints
    Route::get('/posts', [ApiPostController::class, 'index']);

    // Protected endpoints
    Route::middleware(['permission:create posts'])->group(function () {
        Route::post('/posts', [ApiPostController::class, 'store']);
    });

    Route::middleware(['permission:edit posts'])->group(function () {
        Route::put('/posts/{post}', [ApiPostController::class, 'update']);
    });

    Route::middleware(['permission:delete posts'])->group(function () {
        Route::delete('/posts/{post}', [ApiPostController::class, 'destroy']);
    });
});
```

### API Controller

```php
class ApiPostController extends Controller
{
    public function store(Request $request)
    {
        // User already has 'create posts' permission (middleware check)

        $post = Post::create($request->validated());

        return response()->json($post, 201);
    }

    public function update(Request $request, Post $post)
    {
        // Check if user can edit this specific post
        if ($post->user_id !== auth()->id() && !auth()->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $post->update($request->validated());

        return response()->json($post);
    }
}
```

### Token Creation with Abilities

```php
// Login endpoint
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();

    // Get user's permissions
    $permissions = $user->getAllPermissions()->pluck('name')->toArray();

    // Create token with abilities
    $token = $user->createToken('api-token', $permissions)->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
        'roles' => $user->roles,
        'permissions' => $permissions,
    ]);
}
```

---

## Example 6: Wildcard Permissions

Grant access to all permissions in a module.

### Configuration

```php
// config/permitted.php

'wildcards' => [
    'enabled' => true,
    'character' => '*',
],
```

### Seeding

```php
$content = Module::create(['name' => 'Content']);

Permission::create(['name' => 'content.*', 'module_id' => $content->id]); // Wildcard
Permission::create(['name' => 'content.view', 'module_id' => $content->id]);
Permission::create(['name' => 'content.create', 'module_id' => $content->id]);
Permission::create(['name' => 'content.edit', 'module_id' => $content->id]);
Permission::create(['name' => 'content.delete', 'module_id' => $content->id]);

$admin = Role::create(['name' => 'Content Admin']);
$admin->givePermissionTo('content.*'); // Grants all content permissions!
```

### Checking Permissions

```php
$user->hasPermission('content.view');   // true (via wildcard)
$user->hasPermission('content.create'); // true (via wildcard)
$user->hasPermission('content.edit');   // true (via wildcard)
$user->hasPermission('content.delete'); // true (via wildcard)
```

---

## Example 7: School Management (Single-Tenant with Modules)

Traditional single-tenant app with hierarchical module organization.

### Configuration

```php
// config/permitted.php

'multi_tenancy' => [
    'enabled' => false,
],

'modules' => [
    'enabled' => true,
],
```

### Seeding with Sub-Modules

```php
use Williamug\Permitted\Models\SubModule;

class SchoolPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Academic Module
        $academic = Module::create(['name' => 'Academic']);

        $subjects = SubModule::create([
            'module_id' => $academic->id,
            'name' => 'Subjects',
        ]);

        $classes = SubModule::create([
            'module_id' => $academic->id,
            'name' => 'Classes',
        ]);

        Permission::create([
            'name' => 'manage subjects',
            'module_id' => $academic->id,
            'sub_module_id' => $subjects->id,
        ]);

        Permission::create([
            'name' => 'manage classes',
            'module_id' => $academic->id,
            'sub_module_id' => $classes->id,
        ]);

        // Students Module
        $students = Module::create(['name' => 'Students']);

        $admission = SubModule::create([
            'module_id' => $students->id,
            'name' => 'Admission',
        ]);

        Permission::create([
            'name' => 'admit students',
            'module_id' => $students->id,
            'sub_module_id' => $admission->id,
        ]);

        Permission::create([
            'name' => 'view students',
            'module_id' => $students->id,
        ]);

        // Finance Module
        $finance = Module::create(['name' => 'Finance']);
        Permission::create(['name' => 'view fees', 'module_id' => $finance->id]);
        Permission::create(['name' => 'collect fees', 'module_id' => $finance->id]);

        // Create Roles
        $principal = Role::create(['name' => 'Principal']);
        $principal->givePermissionTo(['manage subjects', 'manage classes', 'admit students', 'view students', 'view fees', 'collect fees']);

        $teacher = Role::create(['name' => 'Teacher']);
        $teacher->givePermissionTo(['view students', 'view fees']);

        $accountant = Role::create(['name' => 'Accountant']);
        $accountant->givePermissionTo(['view fees', 'collect fees']);
    }
}
```

### Module-Based Access Control

```php
// Check if user has access to entire module
if (auth()->user()->hasModuleAccess('Academic')) {
    // Show Academic menu
}

// In controller
public function index()
{
    // Get user's accessible modules
    $modules = auth()->user()->getAccessibleModules();

    return view('dashboard', compact('modules'));
}
```

---

## Tips & Best Practices

### 1. Super Admin Role

```php
// config/permitted.php
'super_admin' => [
    'enabled' => true,
    'role_name' => 'Super Admin',
],

// Check in code
if (auth()->user()->isSuperAdmin()) {
    // Bypass all permission checks
}
```

### 2. Permission Caching

```php
// config/permitted.php
'cache' => [
    'enabled' => true,
    'ttl' => 3600, // 1 hour
],

// Clear cache after updating permissions
auth()->user()->forgetCachedPermissions();
```

### 3. Direct Permission Assignments

```php
// Assign permission directly to user (not via role)
$user->givePermissionTo('special permission');

// Check
$user->hasDirectPermission('special permission'); // true
```

### 4. Multiple Role/Permission Checks

```php
// OR check (has any)
$user->hasAnyRole(['Admin', 'Editor', 'Author']); // true if user has at least one
$user->hasAnyPermission(['edit posts', 'delete posts']);

// AND check (has all)
$user->hasAllRoles(['Admin', 'Super User']); // true only if user has both
$user->hasAllPermissions(['view posts', 'create posts']);
```

---

## Next Steps

- Read the [Installation Guide](INSTALLATION.md)
- Check the [API Reference](README.md#api-reference)
- Explore the [Configuration Options](README.md#configuration)
