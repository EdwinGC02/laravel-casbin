<?php

namespace App\Casbin\Middleware;

use App\Casbin\Authorization\CasbinEnforcerFactory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CasbinPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! config('casbin.enabled', true)) {
            return $next($request);
        }

        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(401, 'No se encontró el tenant en sesión.');
        }

        $tenantPrefix = config('casbin.tenant_prefix', 'tenant:');
        $enforcer = CasbinEnforcerFactory::make();

        $allowed = $enforcer->enforce(
            'user:' . Auth::id(),
            $tenantPrefix . $tenantId,
            $module,
            $action
        );

        if (! $allowed) {
            abort(403, $action === 'view'
                ? 'No tienes permisos para ingresar a este módulo.'
                : 'No tienes permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
