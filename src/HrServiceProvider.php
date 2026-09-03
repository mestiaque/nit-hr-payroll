<?php
namespace ME\Hr;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use ME\Hr\Http\Middleware\HrMachineTokenMiddleware;

class HrServiceProvider extends ServiceProvider
{
    public function boot(Router $router)
    {
        $router->aliasMiddleware('hr.machine', HrMachineTokenMiddleware::class);

        if (file_exists(__DIR__ . '/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        }

        if (file_exists(__DIR__ . '/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }

        if (file_exists(__DIR__ . '/routes/portal.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/portal.php');
        }

        // Register the 'employee' auth guard used by the Employee Self-Service
        // Portal, without clobbering a same-named guard the host app may define.
        if (!config('auth.guards.employee')) {
            config(['auth.guards.employee' => [
                'driver' => 'session',
                'provider' => 'hr_employee_logins',
            ]]);
        }
        if (!config('auth.providers.hr_employee_logins')) {
            config(['auth.providers.hr_employee_logins' => [
                'driver' => 'eloquent',
                'model' => \ME\Hr\Models\HrEmployeeLogin::class,
            ]]);
        }

        // Registered unconditionally (not gated behind runningInConsole()) so
        // Artisan::call('hr:sync-legacy-data', ...) also works from a web
        // request — the "Sync" button on the Machine Attendance Log screen
        // relies on that.
        $this->commands([
            \ME\Hr\Console\Commands\SyncLegacyDataCommand::class,
        ]);

        $this->loadViewsFrom(__DIR__ . '/resources/views', 'hr');
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'hr');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/Config' => config_path('hr'),
        ], 'hr-config');

        // Merge hr-permission into main permission config
        $hrPermissions = config('hr-permission');
        if ($hrPermissions && is_array($hrPermissions)) {
            $mainPermissions = config('permission', []);
            // If main permission is using ['modules' => ...] structure, merge into modules
            if (isset($mainPermissions['modules']) && is_array($mainPermissions['modules'])) {
                $mainPermissions['modules']['HR AND COMPLIANCE'] = $hrPermissions['HR AND COMPLIANCE'] ?? [];
                config(['permission' => $mainPermissions]);
            } else {
                // Flat merge (fallback)
                config(['permission' => array_merge($mainPermissions, $hrPermissions)]);
            }
        }
    }

    public function register()
    {
        if (file_exists(__DIR__ . '/Config/config.php')) {
            $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'hr');
        }

        if (file_exists(__DIR__ . '/Config/sidebar.php')) {
            $this->mergeConfigFrom(__DIR__ . '/Config/sidebar.php', 'hr-sidebar');
        }

        if (file_exists(__DIR__ . '/Config/permission.php')) {
            $this->mergeConfigFrom(__DIR__ . '/Config/permission.php', 'hr-permission');
        }

        if (file_exists(__DIR__ . '/Config/checklist.php')) {
            $this->mergeConfigFrom(__DIR__ . '/Config/checklist.php', 'checklist');
        }
    }
}
