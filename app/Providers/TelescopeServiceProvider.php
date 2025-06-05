<?php

namespace App\Providers;

use App\Model\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            /* stop
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
            */
            if ($entry->type == 'request' && isset($entry->content['uri']) && (str_starts_with($entry->content['uri'], '/s') || str_starts_with($entry->content['uri'], '/web') || str_starts_with($entry->content['uri'], '/fonts'))) {
                return false;
            }

            return true;
        });

        Telescope::tag(function (IncomingEntry $entry) {
            if ($entry->isRequest()) {
                $request = $entry->content['request'] ?? null;
                $ip = $request['headers']['HTTP_CF_CONNECTING_IP'] ?? request()->ip();
                return ['ip' => $ip, 'path' => $request['uri'] ?? request()->getRequestUri()];
            }
            return [];
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function () {
            $user = Auth::user();
            return in_array($user->username, ['Aesthetiful']);
        });
    }
}
