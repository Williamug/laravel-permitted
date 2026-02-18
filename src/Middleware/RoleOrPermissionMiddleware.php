<?php

declare(strict_types=1);

namespace Williamug\Permitted\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roleOrPermission
     * @param  string|null  $guard
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $roleOrPermission, ?string $guard = null): Response
    {
        $guard = $guard ?? config('auth.defaults.guard');

        if (!Auth::guard($guard)->check()) {
            abort(403, 'Unauthorized: You must be logged in.');
        }

        $user = Auth::guard($guard)->user();

        if (!method_exists($user, 'hasRole') || !method_exists($user, 'hasPermission')) {
            abort(500, 'User model must use HasRoles and HasPermissions traits.');
        }

        // Support multiple roles/permissions separated by |
        $rolesOrPermissions = explode('|', $roleOrPermission);

        foreach ($rolesOrPermissions as $item) {
            $item = trim($item);
            if ($user->hasRole($item) || $user->hasPermission($item)) {
                return $next($request);
            }
        }

        abort(403, "Unauthorized: You must have one of these roles or permissions: {$roleOrPermission}");
    }
}
