<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\UploadBatch;
use App\Models\IpScan;

echo "=== QUEUE AND BATCH STATUS ===\n\n";

// Check queue
$jobsCount = DB::table('jobs')->count();
$failedJobsCount = DB::table('failed_jobs')->count();

echo "Queue Status:\n";
echo "- Pending jobs: $jobsCount\n";
echo "- Failed jobs: $failedJobsCount\n\n";

// Check batches
$batches = UploadBatch::all();
echo "Batches: " . $batches->count() . "\n";

foreach ($batches as $batch) {
    $ipCount = $batch->ipScans()->count();
    $completedCount = $batch->ipScans()->where('status', 'completed')->count();
    $pendingCount = $batch->ipScans()->where('status', 'pending')->count();
    $scanningCount = $batch->ipScans()->where('status', 'scanning')->count();
    $failedCount = $batch->ipScans()->where('status', 'failed')->count();
    
    echo "\nBatch: {$batch->batch_id}\n";
    echo "  Filename: {$batch->filename}\n";
    echo "  Status: {$batch->status}\n";
    echo "  IPs: $ipCount total\n";
    echo "    - Pending: $pendingCount\n";
    echo "    - Scanning: $scanningCount\n";
    echo "    - Completed: $completedCount\n";
    echo "    - Failed: $failedCount\n";
}

if ($batches->isEmpty()) {
    echo "\nNo batches found.\n";
}
