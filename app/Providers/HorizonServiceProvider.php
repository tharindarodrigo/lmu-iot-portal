<?php

namespace App\Providers;

use App\Domain\Shared\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            if (app()->environment('local') || config('app.observability.open_access', false)) {
                return true;
            }

            if (! $user instanceof User) {
                return false;
            }

            /** @var array<int, string> $allowedEmails */
            $allowedEmails = config('app.observability.allowed_emails', []);

            return in_array($user->email, $allowedEmails, true);
        });
    }
}
