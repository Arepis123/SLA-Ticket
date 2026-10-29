<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\AiBudget;
use App\Services\Ai\ClaudeLanguageModel;
use App\Services\Ai\LanguageModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LanguageModel::class, fn () => new ClaudeLanguageModel(
            config('services.anthropic.api_key'),
            config('services.anthropic.model'),
        ));
        $this->app->bind(AiBudget::class, fn () => new AiBudget(config('services.anthropic.daily_limit')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('staff', fn (User $user): bool => $user->isStaff());

        // A rejected push does not fail the queued job, so record it or it goes unnoticed.
        // Expired subscriptions are deleted by the package and are expected.
        Event::listen(function (NotificationFailed $event): void {
            if (! $event->report->isSubscriptionExpired()) {
                Log::warning('Browser push rejected', [
                    'endpoint' => parse_url($event->subscription->endpoint, PHP_URL_HOST),
                    'status' => $event->report->getResponse()?->getStatusCode(),
                    'reason' => (string) $event->report->getResponse()?->getBody() ?: $event->report->getReason(),
                ]);
            }
        });

        // `composer run dev` also runs the scheduler so `sla:check` fires every minute locally.
        DevCommands::artisan('schedule:work', 'scheduler');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
