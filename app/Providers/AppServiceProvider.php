<?php

namespace App\Providers;

use Illuminate\Http\Request;
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
            $requestHost = Request::getHost();

            if ($appUrl === $requestHost) {
                URL::forceScheme('https');
            }
        }
    }
}
