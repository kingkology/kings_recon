# Batch Processing Implementation

## Overview

The IP scanning system has been updated to use batch processing instead of individual job processing. This means multiple IPs are processed together in a single job, significantly improving efficiency and reducing queue overhead.

## Architecture

### Before (Individual Jobs)
```
Upload 100 IPs
  → Create 100 separate jobs in queue
  → Process 1 job per scheduler run
  → Total time: ~100 scheduler cycles
```

### After (Batch Processing)
```
Upload 100 IPs
  → Create 1 batch job for those IPs
  → Batch job processes 5 IPs per execution
  → If more IPs remain, dispatch another batch job
  → Total time: ~20 scheduler cycles
```

## Key Components

### 1. **ProcessIpScanBatch Job** (`app/Jobs/ProcessIpScanBatch.php`)

The new batch processing job that handles multiple IPs in a single execution:

- **Batch Size**: Processes 5 IPs per job (configurable via `$batchSize`)
- **Timeout**: 10 minutes per batch job (600 seconds)
- **Retries**: 3 attempts if job fails
- **Behavior**: 
  - Fetches up to 5 pending IPs
  - Scans each IP in sequence
  - Logs results and errors
  - If more IPs remain, dispatches another batch job with 5-second delay
  - When complete, updates batch status to "completed" or "completed_with_errors"

### 2. **Updated Controller** (`app/Http/Controllers/IpValidatorController.php`)

Changed from dispatching individual jobs to dispatching a single batch job:

```php
// Old: Dispatched one job per IP
foreach ($ipScans as $ipScan) {
    ProcessIpScan::dispatch($ipScan);
}

// New: Dispatch one batch job for all IPs
ProcessIpScanBatch::dispatch($batchId);
```

### 3. **Enhanced Queue Processing Command** (`app/Console/Commands/ProcessQueue.php`)

Updated to process multiple jobs per scheduler cycle:

- Changed `--once` from `true` to `false` to allow multiple jobs
- Added `--max-jobs 10` to process up to 10 batch jobs per scheduler run
- Timeout reduced to 55 seconds (leaves 5-second buffer before scheduler timeout)

## Processing Flow

1. **File Upload**
   - User uploads file with IPs
   - IPs imported into `ip_scans` table with status "pending"
   - Single `ProcessIpScanBatch` job dispatched with batch_id

2. **Batch Processing** (Every minute via scheduler)
   ```
   Queue Command Execution
   ├─ ProcessIpScanBatch Job 1
   │  ├─ Get 5 pending IPs
   │  ├─ Scan each IP
   │  └─ Dispatch new batch if more IPs remain
   ├─ ProcessIpScanBatch Job 2
   │  ├─ Get 5 pending IPs
   │  ├─ Scan each IP
   │  └─ Dispatch new batch if more IPs remain
   └─ ProcessIpScanBatch Job 3
      └─ ...
   ```

3. **Completion**
   - When all IPs are scanned, batch status updated to "completed"
   - If any IPs failed, status set to "completed_with_errors"
   - Batch record includes `completed_at` timestamp

## Configuration

### Batch Size
Adjust the number of IPs processed per job in `ProcessIpScanBatch.php`:

```php
public int $batchSize = 5; // Change to desired number
```

**Recommendations:**
- **5 IPs**: Conservative, safe for shared hosting (default)
- **10 IPs**: Moderate, good balance between speed and resource usage
- **20 IPs**: Aggressive, requires more resources but faster processing

### Delay Between Batches
Control delay before dispatching the next batch:

```php
ProcessIpScanBatch::dispatch($this->batchId)->delay(now()->addSeconds(5));
```

Adjust the seconds value as needed.

### Max Jobs per Scheduler Run
In `ProcessQueue.php`, change `--max-jobs` value:

```php
'--max-jobs' => 10,  // Process up to 10 batch jobs per run
```

## Monitoring

### Logs
Check processing status in `storage/logs/laravel.log`:

```
[2026-01-08 10:32:31] local.INFO: Processing batch of 5 IPs for batch <batch_id>
[2026-01-08 10:32:33] local.INFO: IP scan completed for 160.119.246.192
[2026-01-08 10:32:35] local.INFO: Batch processing completed: 5 successful, 0 failed for batch <batch_id>
[2026-01-08 10:32:36] local.INFO: Dispatching new batch job for remaining 95 IPs in batch <batch_id>
```

### Queue Status
Check remaining jobs in database:

```php
php artisan tinker
>>> DB::table('jobs')->count()  // Total pending jobs
>>> DB::table('failed_jobs')->count()  // Failed jobs
```

### Batch Status
Monitor batch progress:

```php
php artisan tinker
>>> \App\Models\UploadBatch::all()
>>> \App\Models\IpScan::where('batch_id', '<batch_id>')->get()
```

## Performance Benefits

1. **Reduced Overhead**: 100 IPs = 1 batch job instead of 100 individual jobs
2. **Faster Processing**: Multiple IPs processed in single command execution
3. **Better Resource Management**: Fewer context switches and queue lookups
4. **Scalability**: Can handle larger batches by adjusting batch size

## Example Processing Times

**100 IPs with batch size = 5**

| Approach | Jobs | Scheduler Cycles | Est. Time (1/min) | Est. Time (5/min) |
|----------|------|------------------|-------------------|-------------------|
| Individual | 100 | 100 | ~100 min | ~20 min |
| Batch (5 IPs) | 20 | 20 | ~20 min | ~4 min |
| Batch (10 IPs) | 10 | 10 | ~10 min | ~2 min |

*Assumes scheduler runs every 1 minute and can process up to 5 jobs per run with `--max-jobs 5`*

## Backward Compatibility

The old `ProcessIpScan` job is still available but no longer used for new uploads. Existing functionality remains unchanged:

- Views work as before
- Database structure unchanged
- Progress tracking via `status` field unchanged
- Reports generated the same way

## Troubleshooting

### Batch Not Processing
- Check `queue:process-batch` command is running (scheduled)
- Check `storage/logs/laravel.log` for errors
- Verify database connection is working

### IPs Stuck in "Pending"
- Check failed_jobs table for errors
- Verify IpScanService can access network resources
- Check job timeout settings (may be too short)

### Memory Issues
- Reduce batch size (e.g., from 10 to 5)
- Check IpScanService for memory leaks
- Monitor `--memory 128` limit in ProcessQueue command

## Future Enhancements

- [ ] Add progress percentage to batch display
- [ ] Implement concurrent IP scanning within batch (using ReactPHP)
- [ ] Add batch job priority queuing
- [ ] Create dashboard showing real-time processing stats
