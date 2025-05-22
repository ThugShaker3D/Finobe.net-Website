<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        if (env('APP_ENV') !== 'local') {
            $appUrl = parse_url(env('APP_URL'), PHP_URL_HOST);
            $requestHost = request()->getHost();
            dd($requestHost);

            if ($appUrl === $requestHost && request()->is('telescope*')) {
                URL::forceScheme('https');
            }
        }
    }
}
