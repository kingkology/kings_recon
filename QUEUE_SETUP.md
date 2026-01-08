# Queue Processing Cron Setup Guide

## Overview
This application uses Laravel's queue system with a database driver to process IP scans asynchronously. A cron job runs the queue worker every minute to process pending jobs.

## Configuration Details

### Job Configuration
- **Queue Connection**: Database (configured in `.env`)
- **Job Class**: `App\Jobs\ProcessIpScan`
- **Timeout**: 300 seconds (5 minutes)
- **Retries**: 3 attempts
- **Queue Table**: `jobs` (created by Laravel migrations)

### Scheduler Setup

The Laravel Console Kernel (`app/Console/Kernel.php`) is configured with:
```php
$schedule->command('queue:work --once --queue=default --tries=3 --timeout=300')
    ->everyMinute()
    ->withoutOverlapping()
    ->sendOutputTo(storage_path('logs/queue-worker.log'));
```

## System Cron Configuration

### Linux/macOS

Add the following to your system crontab (run `crontab -e`):

```bash
* * * * * cd /path/to/public_ip_validator && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Important**: Replace `/path/to/public_ip_validator` with the actual absolute path to your project.

### Windows (Using Task Scheduler)

1. Open Task Scheduler
2. Create a new Basic Task:
   - **Name**: "Process IP Scans Queue"
   - **Trigger**: Repeat every 1 minute, indefinitely
   - **Action**: Start a program
     - **Program**: `C:\Program Files\PHP\php.exe` (adjust PHP path as needed)
     - **Arguments**: `artisan schedule:run`
     - **Start in**: `C:\Users\SPACE\Herd\public_ip_validator` (your project root)

3. Configure the task to:
   - Run whether user is logged in or not
   - Run with highest privileges (if needed)
   - Set history logging for debugging

### Alternative: Using Supervisor (Production Recommended)

For production environments, use Supervisor for more reliable queue processing:

```ini
[program:ip_validator_queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/public_ip_validator/artisan queue:work --queue=default --tries=3 --timeout=300
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/public_ip_validator/storage/logs/queue-worker.log
```

## Monitoring & Troubleshooting

### Check Queue Status
```bash
php artisan queue:failed          # View failed jobs
php artisan queue:retry all       # Retry all failed jobs
php artisan queue:flush           # Clear all jobs from queue
```

### View Logs
- **Queue Worker Logs**: `storage/logs/queue-worker.log`
- **Laravel Logs**: `storage/logs/laravel.log`

### Common Issues

1. **Jobs not processing**
   - Verify `QUEUE_CONNECTION=database` in `.env`
   - Check that the `jobs` table exists: `php artisan migrate`
   - Ensure PHP can execute system crons/tasks

2. **Permission errors**
   - Make sure the `storage/logs` directory is writable
   - Linux: `chmod -R 755 storage/logs`

3. **Jobs timing out**
   - Increase timeout if IP scans take longer: adjust `$timeout` in `ProcessIpScan.php`
   - Verify your network connectivity for port scanning

## Testing Queue Processing

1. **Manually dispatch a job**:
   ```bash
   php artisan tinker
   >>> $ipScan = \App\Models\IpScan::first();
   >>> \App\Jobs\ProcessIpScan::dispatch($ipScan);
   ```

2. **Process queued jobs manually**:
   ```bash
   php artisan queue:work --once
   ```

3. **Monitor live queue processing**:
   ```bash
   php artisan queue:work
   ```

## Environment Variables Reference

From your `.env`:
- `QUEUE_CONNECTION=database` - Use database driver for queue
- `DB_CONNECTION=mysql` - Database connection for storing jobs
- `LOG_CHANNEL=stack` - Logging configuration

## Security Notes

- Ensure cron/scheduler runs with appropriate user permissions
- Restrict access to `/artisan` command
- Monitor `storage/logs/queue-worker.log` for errors
- Keep database credentials secure in `.env`
