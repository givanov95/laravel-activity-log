<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use Givanov95\LaravelActivityLog\Console\PruneActivityLogCommand;
use Givanov95\LaravelActivityLog\Contracts\ResolvesActor;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/activity-log.php', 'activity-log');

        // Scoped: one logger (and one batch uuid) per request or job.
        $this->app->scoped(ActivityLogger::class);

        $this->app->bind(ResolvesActor::class, fn ($app) => $app->make(config('activity-log.actor_resolver')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneActivityLogCommand::class]);

            $this->publishes([
                __DIR__.'/../config/activity-log.php' => config_path('activity-log.php'),
            ], 'activity-log-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'activity-log-migrations');
        }
    }
}
