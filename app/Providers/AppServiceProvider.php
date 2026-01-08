<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
        // Schedule queue processing every minute
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('queue:process-batch')
                ->everyMinute()
                ->sendOutputTo(storage_path('logs/queue-process.log'));
        });
    }
}
