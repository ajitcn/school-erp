<?php

namespace App\Providers;

use App\Models\Notice;
use App\Models\NotificationLog;
use App\Services\PlatformConfigurationService;
use App\Services\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $emailInput = $request->input('email');
            $email = is_string($emailInput)
                ? Str::transliterate(Str::lower(trim(substr($emailInput, 0, 255))))
                : '';

            return [
                Limit::perMinute(5)->by('login:identity:'.$request->ip().':'.hash('sha256', $email)),
                Limit::perMinute(30)->by('login:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $emailInput = $request->input('email');
            $email = is_string($emailInput)
                ? Str::transliterate(Str::lower(trim(substr($emailInput, 0, 255))))
                : '';

            return [
                Limit::perMinute(5)->by('password-reset:identity:'.$request->ip().':'.hash('sha256', $email)),
                Limit::perMinute(30)->by('password-reset:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('registration', function (Request $request): array {
            return [
                Limit::perHour(5)->by('registration:hour:'.$request->ip()),
                Limit::perDay(10)->by('registration:day:'.$request->ip()),
            ];
        });

        RateLimiter::for('biometric', function (Request $request): array {
            $deviceToken = (string) $request->header('X-Device-Token', '');

            return [
                Limit::perMinute(12)->by('biometric:device:'.hash('sha256', $deviceToken)),
                Limit::perMinute(60)->by('biometric:ip:'.$request->ip()),
            ];
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
            if ($event->channel === 'mail') {
                app(PlatformConfigurationService::class)->applyMailConfiguration();
            }
        });

        View::composer('components.admin.header', function ($view): void {
            $user = Auth::user();
            $notifications = collect();
            $unreadNotificationCount = 0;
            $messages = collect();
            $unreadMessageCount = 0;

            if ($user && ! $user->is_platform_super_admin && app(TenantContext::class)->id()) {
                $notifications = NotificationLog::query()
                    ->where('user_id', $user->id)
                    ->latest()
                    ->limit(5)
                    ->get();
                $unreadNotificationCount = NotificationLog::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                $branchIds = $user->accessibleBranchIds();
                $noticeQuery = Notice::query()
                    ->visibleTo($user)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->when($branchIds !== null, fn ($query) => $query->where(fn ($scoped) => $scoped
                        ->whereNull('branch_id')
                        ->orWhereIn('branch_id', $branchIds)));

                if (Schema::hasTable('notice_reads')) {
                    $unreadMessages = (clone $noticeQuery)->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id));
                    $messages = (clone $unreadMessages)->latest('published_at')->limit(5)->get();
                    $unreadMessageCount = (clone $unreadMessages)->count();
                } else {
                    $messages = (clone $noticeQuery)->latest('published_at')->limit(5)->get();
                    $unreadMessageCount = (clone $noticeQuery)->count();
                }
            }

            $view->with(compact('notifications', 'unreadNotificationCount', 'messages', 'unreadMessageCount'));
        });
    }
}
