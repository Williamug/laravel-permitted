<?php

declare(strict_types=1);

namespace Williamug\Permitted;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Williamug\Permitted\Middleware\PermissionMiddleware;
use Williamug\Permitted\Middleware\RoleMiddleware;
use Williamug\Permitted\Middleware\RoleOrPermissionMiddleware;

class PermittedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/permitted.php',
            'permitted'
        );

        // Register the facade
        $this->app->singleton('permitted', function ($app) {
            return new Permitted($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/permitted.php' => config_path('permitted.php'),
        ], 'permitted-config');

        // Load and publish migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'permitted-migrations');

        // Register middleware
        $this->registerMiddleware();

        // Register Blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware(
            config('permitted.middleware.role', 'role'),
            RoleMiddleware::class
        );

        $router->aliasMiddleware(
            config('permitted.middleware.permission', 'permission'),
            PermissionMiddleware::class
        );

        $router->aliasMiddleware(
            'role_or_permission',
            RoleOrPermissionMiddleware::class
        );
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @role('admin')
        Blade::if('role', function ($role) {
            return auth()->check() && auth()->user()->hasRole($role);
        });

        // @hasrole('admin')
        Blade::if('hasrole', function ($role) {
            return auth()->check() && auth()->user()->hasRole($role);
        });

        // @hasanyrole(['admin', 'editor'])
        Blade::if('hasanyrole', function ($roles) {
            if (! is_array($roles)) {
                $roles = explode('|', $roles);
            }

            return auth()->check() && auth()->user()->hasAnyRole($roles);
        });

        // @hasallroles(['admin', 'editor'])
        Blade::if('hasallroles', function ($roles) {
            if (! is_array($roles)) {
                $roles = explode('|', $roles);
            }

            return auth()->check() && auth()->user()->hasAllRoles($roles);
        });

        // @permission('edit posts')
        Blade::if('permission', function ($permission) {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        // @haspermission('edit posts')
        Blade::if('haspermission', function ($permission) {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        // @hasanypermission(['edit posts', 'delete posts'])
        Blade::if('hasanypermission', function ($permissions) {
            if (! is_array($permissions)) {
                $permissions = explode('|', $permissions);
            }

            return auth()->check() && auth()->user()->hasAnyPermission($permissions);
        });

        // @hasallpermissions(['edit posts', 'delete posts'])
        Blade::if('hasallpermissions', function ($permissions) {
            if (! is_array($permissions)) {
                $permissions = explode('|', $permissions);
            }

            return auth()->check() && auth()->user()->hasAllPermissions($permissions);
        });

        // @superadmin
        Blade::if('superadmin', function () {
            return auth()->check() && auth()->user()->isSuperAdmin();
        });

        // @unlessrole('guest')
        Blade::if('unlessrole', function ($role) {
            return auth()->check() && ! auth()->user()->hasRole($role);
        });

        // @unlesspermission('edit posts')
        Blade::if('unlesspermission', function ($permission) {
            return auth()->check() && ! auth()->user()->hasPermission($permission);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['permitted'];
    }
}
