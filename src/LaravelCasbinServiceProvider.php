<?php

namespace Sodeker\LaravelCasbin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Casbin\Enforcer;
use Sodeker\LaravelCasbin\Application\Services\PermissionService;
use Sodeker\LaravelCasbin\Application\Services\TenantRolePolicyWriter;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
use Sodeker\LaravelCasbin\Domain\Contracts\TenantContextInterface;
use Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface;
use Sodeker\LaravelCasbin\Infrastructure\Casbin\EnforcerFactory;
use Sodeker\LaravelCasbin\Infrastructure\Tenancy\SessionTenantContext;

class LaravelCasbinServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/casbin.php', 'casbin');

        $this->app->singleton(Enforcer::class, function () {
            return EnforcerFactory::make();
        });

        // Singleton: el servicio es sin estado (el resultado de cada chequeo
        // ya se cachea) y así todas las inyecciones comparten el mismo
        // enforcer perezoso en lugar de crear una instancia por resolución.
        $this->app->singleton(
            PermissionServiceInterface::class,
            PermissionService::class
        );

        // Tenant de la petición. Por defecto la sesión, que es lo que el
        // paquete leía de forma fija. Las apps que resuelven el tenant por
        // petición (p. ej. en la URL, con varias pestañas en tenants
        // distintos) DEBEN reemplazar este binding: la sesión es una sola por
        // navegador y autorizaría contra el tenant de la última pestaña.
        $this->app->bind(
            TenantContextInterface::class,
            SessionTenantContext::class
        );

        // Escritura de políticas acotada al dominio. Sin estado, por eso
        // singleton.
        $this->app->singleton(
            TenantRolePolicyWriterInterface::class,
            TenantRolePolicyWriter::class
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $sourceCasbinPath = __DIR__ . '/../casbin';
            $targetCasbinPath = base_path('casbin');

            if (! File::exists($targetCasbinPath)) {
                File::copyDirectory($sourceCasbinPath, $targetCasbinPath);
            }

            $targetConfigPath = config_path('casbin.php');
            if (! File::exists($targetConfigPath)) {
                File::copy(__DIR__ . '/../config/casbin.php', $targetConfigPath);
            }
        }

        $this->publishes([
            __DIR__ . '/../casbin' => base_path('casbin'),
        ], 'casbin');

        $this->publishes([
            __DIR__ . '/../config/casbin.php' => config_path('casbin.php'),
        ], 'casbin-config');

        $this->publishes([
            __DIR__ . '/../casbin/model.conf' => base_path('casbin/model.conf'),
        ], 'casbin-model');

        $this->publishes([
            __DIR__ . '/../casbin/migrations' => base_path('casbin/migrations'),
        ], 'casbin-migrations');

        $this->publishes([
            __DIR__ . '/../casbin/Authorization/CasbinEnforcerFactory.php' => base_path('casbin/Authorization/CasbinEnforcerFactory.php'),
            __DIR__ . '/../casbin/Middleware/CasbinPermissionMiddleware.php' => base_path('casbin/Middleware/CasbinPermissionMiddleware.php'),
            __DIR__ . '/../casbin/README.md' => base_path('casbin/README.md'),
            __DIR__ . '/../casbin/model.conf' => base_path('casbin/model.conf'),
        ], 'casbin-structure');

        $this->app['router']->aliasMiddleware(
            'permission',
            \Sodeker\LaravelCasbin\Interfaces\Middleware\CheckPermission::class
        );

        $this->app['router']->aliasMiddleware(
            'casbin',
            \Sodeker\LaravelCasbin\Interfaces\Middleware\CheckPermission::class
        );
    }
}