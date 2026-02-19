<?php

declare(strict_types=1);

namespace Williamug\Permitted\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): Response
    {
        $guard = $guard ?? config('auth.defaults.guard');

        if (! Auth::guard($guard)->check()) {
            abort(403, 'Unauthorized: You must be logged in.');
        }

        $user = Auth::guard($guard)->user();

        if (! method_exists($user, 'hasPermission')) {
            abort(500, 'User model must use HasPermissions trait.');
        }

        // Support multiple permissions separated by |
        $permissions = explode('|', $permission);

        foreach ($permissions as $permissionName) {
            if ($user->hasPermission(trim($permissionName))) {
                return $next($request);
            }
        }

        abort(403, "Unauthorized: You must have one of these permissions: {$permission}");
    }
}
