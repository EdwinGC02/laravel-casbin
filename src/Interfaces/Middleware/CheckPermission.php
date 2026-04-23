<?php

namespace Sodeker\LaravelCasbin\Interfaces\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $resource, string $action = 'view'): Response
    {
        if (!Config::get('casbin.enabled', true)) {
            return $next($request);
        }

        if (!Auth::check()) {
            abort(401);
        }

        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$user || !$tenantId) {
            abort(401);
        }

        $service = app(PermissionServiceInterface::class);

        if (!$service->can($user->id, $tenantId, $resource, $action)) {
            abort(403);
        }

        return $next($request);
    }
}