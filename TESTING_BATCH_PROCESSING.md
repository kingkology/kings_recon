# Testing & Monitoring the Batch Processing System

## Testing the Implementation

### Step 1: Clear Previous Data (Optional)
```bash
php artisan tinker

# View existing batches
>>> \App\Models\UploadBatch::all()

# Delete test data if needed
>>> \App\Models\UploadBatch::truncate()
>>> \App\Models\IpScan::truncate()
>>> DB::table('jobs')->truncate()
```

### Step 2: Create a Test File
Create `test_ips.txt`:
```
8.8.8.8
1.1.1.1
208.67.222.222
8.8.4.4
1.0.0.1
208.67.220.220
9.9.9.9
149.112.112.112
1.1.1.2
1.0.0.2
```

Save this file in your workspace directory.

### Step 3: Upload the Test File
1. Open your browser to the application
2. Go to IP Validator → Upload
3. Select `test_ips.txt`
4. Click Upload
5. Note the Batch ID from the response

### Step 4: Monitor Processing

#### Option A: Watch the Logs
```bash
# Terminal 1: Watch logs
Get-Content storage/logs/laravel.log -Tail 20 -Wait
```

You should see:
```
[2026-01-08 10:32:31] local.INFO: Queue process batch started at 2026-01-08 10:32:31
[2026-01-08 10:32:31] local.INFO: Processing batch of 5 IPs for batch <batch_id>
[2026-01-08 10:32:33] local.INFO: IP scan completed for 8.8.8.8
[2026-01-08 10:32:34] local.INFO: IP scan completed for 1.1.1.1
[2026-01-08 10:32:35] local.INFO: IP scan completed for 208.67.222.222
[2026-01-08 10:32:36] local.INFO: IP scan completed for 8.8.4.4
[2026-01-08 10:32:37] local.INFO: IP scan completed for 1.0.0.1
[2026-01-08 10:32:37] local.INFO: Batch processing completed: 5 successful, 0 failed for batch <batch_id>
[2026-01-08 10:32:37] local.INFO: Dispatching new batch job for remaining 5 IPs in batch <batch_id>
```

#### Option B: Use Tinker
```bash
php artisan tinker

# Check batch status
>>> $batch = \App\Models\UploadBatch::latest()->first()
>>> $batch->status
>>> $batch->progress  # Shows percentage complete

# Check IP scan progress
>>> \App\Models\IpScan::where('batch_id', $batch->batch_id)->pluck('status')

# Count by status
>>> \App\Models\IpScan::where('batch_id', $batch->batch_id)->get()->groupBy('status')->map->count()

# Check queue
>>> DB::table('jobs')->count()  # Pending jobs
>>> DB::table('failed_jobs')->count()  # Failed jobs
```

#### Option C: Check Queue Directly
```bash
php artisan queue:work --dry-run
```

### Step 5: Verify Results

#### Via Web Interface
1. Go to Dashboard/Batches
2. Should show your batch with status "processing" → "completed"
3. Click on batch to see individual IP scan results

#### Via Tinker
```bash
php artisan tinker

# Get specific batch
>>> $batch = \App\Models\UploadBatch::where('batch_id', '<your_batch_id>')->first()
>>> $batch->toArray()

# Get all scanned IPs
>>> $ips = \App\Models\IpScan::where('batch_id', '<your_batch_id>')->get()
>>> $ips->each(fn($ip) => echo "{$ip->ip_address}: {$ip->status}\n")

# Get vulnerable ports found
>>> $ips->where('status', 'completed')->first()?->vulnerable_ports
```

## Performance Metrics

### Measurement 1: Processing Time
```bash
# Note: Run this in terminal 1
time php artisan queue:process-batch

# Output shows:
# Real time: ~55 seconds (since --timeout 55)
# Actual work: ~10-30 seconds (varies by IPs/network)
```

### Measurement 2: Jobs Processed Per Run
```bash
# Clear logs first
rm storage/logs/queue-process.log

# Run queue command
php artisan queue:process-batch

# Check how many batch jobs processed
Get-Content storage/logs/queue-process.log | Select-String "Processing batch" | wc -l
# Output: Number of batch jobs processed (should be 1-10)
```

### Measurement 3: Total Processing Time
```bash
# Upload time + Processing time = Total time

# Check timestamps in database
php artisan tinker
>>> $batch = \App\Models\UploadBatch::latest()->first()
>>> $created = $batch->created_at
>>> $completed = $batch->completed_at
>>> $completed->diffInSeconds($created)  # Shows total seconds

# For 10 IPs with batchSize=5:
# Expected: ~2 minutes (2 scheduler cycles)
```

## Monitoring Dashboard

### Create a Monitoring Script
Create `monitor.php`:
```php
<?php
// Simple monitoring script to track batch processing
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UploadBatch, App\Models\IpScan;

while (true) {
    system('clear'); // or 'cls' on Windows
    
    echo "=== BATCH PROCESSING MONITOR ===\n";
    echo "Time: " . now()->format('Y-m-d H:i:s') . "\n";
    echo "==============================\n\n";
    
    // Latest batch
    $batch = UploadBatch::latest()->first();
    if ($batch) {
        echo "Latest Batch: {$batch->batch_id}\n";
        echo "Filename: {$batch->filename}\n";
        echo "Status: {$batch->status}\n";
        echo "Total IPs: {$batch->total_ips}\n";
        echo "Progress: {$batch->progress}%\n";
        echo "Started: " . $batch->started_at?->diffForHumans() . "\n";
        echo "Completed: " . $batch->completed_at?->diffForHumans() . "\n";
        echo "\n";
        
        // Status breakdown
        $statuses = IpScan::where('batch_id', $batch->batch_id)
            ->get()
            ->groupBy('status')
            ->map->count();
        
        foreach ($statuses as $status => $count) {
            echo "  $status: $count\n";
        }
    }
    
    // Queue status
    echo "\n=== QUEUE STATUS ===\n";
    echo "Pending jobs: " . DB::table('jobs')->count() . "\n";
    echo "Failed jobs: " . DB::table('failed_jobs')->count() . "\n";
    
    // Last log entries
    echo "\n=== RECENT LOG ENTRIES ===\n";
    $logs = file('storage/logs/laravel.log');
    echo implode("\n", array_slice($logs, -5));
    
    echo "\n\n[Press Ctrl+C to exit, updates every 5 seconds]\n";
    sleep(5);
}
```

Run with:
```bash
php monitor.php
```

## Expected Behavior

### During Processing
✓ Status shows "processing"
✓ Individual IPs show "scanning" or "completed"
✓ Queue has 1-10 pending jobs (ProcessIpScanBatch)
✓ New batch jobs dispatched with 5-second delays
✓ Logs show "Processing batch of 5 IPs"

### After Completion
✓ Status shows "completed" or "completed_with_errors"
✓ All IPs show "completed" or "failed"
✓ Queue is empty (0 jobs)
✓ All timestamps populated (created_at, started_at, scanned_at, completed_at)
✓ Logs show "Batch processing completed: X successful, Y failed"

## Troubleshooting Tests

### Test 1: Verify Job Dispatch
```bash
php artisan tinker

# Add debug logging
>>> \Illuminate\Support\Facades\Log::info('Debug test at ' . now());

# Upload file and check logs
>>> tail -f storage/logs/laravel.log | grep "Processing batch"
```

### Test 2: Check Network Issues
```bash
# Manually test if scanning works
php artisan tinker
>>> $service = new \App\Services\IpScanService()
>>> $result = $service->pingHost('8.8.8.8')
>>> var_dump($result)  # Should show is_online: true
```

### Test 3: Test Single IP Scan
```bash
# Create and run a manual batch job
php artisan tinker

>>> $batch = \App\Models\UploadBatch::create([
    'filename' => 'test.txt',
    'total_ips' => 1,
    'status' => 'processing'
])

>>> \App\Models\IpScan::create([
    'batch_id' => $batch->batch_id,
    'ip_address' => '8.8.8.8',
    'status' => 'pending'
])

>>> \App\Jobs\ProcessIpScanBatch::dispatch($batch->batch_id)->onQueue('default')

# Check if processed
>>> \App\Models\IpScan::where('batch_id', $batch->batch_id)->first()->status
# Should eventually show "completed"
```

### Test 4: Verify Batch Job Chaining
```bash
# Upload file with many IPs (50+) and watch logs
tail -f storage/logs/laravel.log | grep -i "dispatching"

# Should show multiple "Dispatching new batch job" entries
# indicating the auto-dispatch chain is working
```

## Performance Tuning

### If Processing is Too Slow
1. Increase batch size: `batchSize = 10`
2. Increase max jobs: `'--max-jobs' => 20`
3. Check network connectivity to target IPs
4. Review timeout settings

### If Using Too Much Memory
1. Decrease batch size: `batchSize = 3`
2. Check if IpScanService has memory leaks
3. Reduce `--memory` limit (may cause crashes)

### If Jobs Keep Failing
1. Check `storage/logs/laravel.log` for specific errors
2. Verify database connectivity
3. Check if IPs are valid
4. Increase `--timeout` if scans take longer

## Next Steps

1. Upload test file
2. Watch logs for batch processing
3. Verify results in database
4. Check performance metrics
5. Adjust batch size/max-jobs if needed
6. Monitor in production with regular uploads

That's it! Your batch processing system is ready to use.
