<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateBiometricDevice;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, ResolveTenant::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, AuthenticateApiToken::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, EnsurePortalAccess::class);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'tenant' => ResolveTenant::class,
            'platform' => EnsurePlatformAdmin::class,
            'portal.access' => EnsurePortalAccess::class,
            'api.token' => AuthenticateApiToken::class,
            'device.token' => AuthenticateBiometricDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscriptions:expire')->dailyAt('00:05')->withoutOverlapping();
        $schedule->command('fees:send-due-reminders')->dailyAt('08:00')->withoutOverlapping();
    })->create();
