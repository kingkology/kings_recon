<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessQueue extends Command
{
    protected $signature = 'queue:process-batch';
    protected $description = 'Process a batch of queued jobs (Hostinger compatible)';

    public function handle()
    {
        try {
            // Log execution
            \Illuminate\Support\Facades\Log::info('Queue process batch started at ' . now());
            
            // Process queued jobs using database queue
            // Use --max-jobs to process multiple batch jobs per scheduler execution
            // This allows processing up to 10 batch jobs in a single command execution
            $exitCode = Artisan::call('queue:work', [
                '--timeout' => 55,  // Leave 5 seconds buffer before scheduler timeout
                '--memory' => 128,
                '--tries' => 3,
                '--max-jobs' => 10  // Process max 10 batch jobs per scheduler run
            ]);

            \Illuminate\Support\Facades\Log::info('Queue process batch completed with exit code: ' . $exitCode);
            $this->info('Processed queue batch');
            return $exitCode;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Queue process batch failed: ' . $e->getMessage());
            $this->error('Queue process batch failed: ' . $e->getMessage());
            return 1;
        }
    }
}
