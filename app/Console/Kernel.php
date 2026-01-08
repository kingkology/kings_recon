<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Log scheduler runs for debugging
        $schedule->call(function () {
            Log::debug('Scheduler tick at ' . now());
        })->everyMinute();
        
        // Queue processing every minute (Hostinger compatible)
        $schedule->command('queue:process-batch')
            ->everyMinute()
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/queue-process.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
