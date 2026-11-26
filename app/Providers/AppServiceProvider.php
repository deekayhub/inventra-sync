<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($request->ip().'|'.$request->input('email')));
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($request->ip().'|'.$request->input('email')));
        RateLimiter::for('tenant-operations', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) (tenant()?->getTenantKey() ?? 'central').'|'.$request->user()?->getKey()));

        Queue::failing(function (JobFailed $event): void {
            Log::channel((string) config('queue.failed_alert_channel', 'stack'))->error('Queue job failed', [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'message' => $event->exception->getMessage(),
            ]);
        });
    }
}
