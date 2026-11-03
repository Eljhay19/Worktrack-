<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('worktrack-api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });
        RateLimiter::for('worktrack-email-code', function (Request $request): Limit {
            return Limit::perMinutes(10, 5)->by($request->ip().'|'.hash('sha256', (string) $request->input('browser_id')));
        });
        RateLimiter::for('worktrack-email-code-check', function (Request $request): Limit {
            return Limit::perMinute(12)->by($request->ip().'|'.hash('sha256', (string) $request->input('browser_id')));
        });
    }
}
