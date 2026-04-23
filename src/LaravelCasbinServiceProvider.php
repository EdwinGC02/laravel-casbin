<?php

namespace Sodeker\LaravelCasbin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Casbin\Enforcer;
use Sodeker\LaravelCasbin\Application\Services\PermissionService;
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
use Sodeker\LaravelCasbin\Infrastructure\Casbin\EnforcerFactory;

class LaravelCasbinServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/casbin.php', 'casbin');

        $this->app->singleton(Enforcer::class, function () {
            return EnforcerFactory::make();
        });

        $this->app->bind(
            PermissionServiceInterface::class,
            PermissionService::class
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