<?php

namespace App\Jobs;

use App\Models\IpScan;
use App\Models\UploadBatch;
use App\Services\IpScanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIpScanBatch implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout for batch
    public int $tries = 3;
    public int $batchSize = 5; // Process 5 IPs per job

    private string $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $uploadBatch = UploadBatch::where('batch_id', $this->batchId)->firstOrFail();
            
            // Get pending IPs for this batch (limited to batch size)
            $pendingIps = IpScan::where('batch_id', $this->batchId)
                ->where('status', 'pending')
                ->limit($this->batchSize)
                ->get();

            if ($pendingIps->isEmpty()) {
                Log::info("No pending IPs found for batch {$this->batchId}");
                $this->checkBatchCompletion($uploadBatch);
                return;
            }

            Log::info("Processing batch of {$pendingIps->count()} IPs for batch {$this->batchId}");

            $scanService = new IpScanService();
            $successCount = 0;
            $failureCount = 0;

            foreach ($pendingIps as $ipScan) {
                try {
                    // Update status to scanning
                    $ipScan->update(['status' => 'scanning']);

                    // Perform the scan
                    $result = $scanService->performFullScan($ipScan->ip_address);

                    // Update the IP scan record with results
                    $ipScan->update([
                        'is_online' => $result['is_online'],
                        'ping_time' => $result['ping_time'],
                        'open_ports' => $result['open_ports'],
                        'vulnerable_ports' => $result['vulnerable_ports'],
                        'scan_details' => $result['scan_details'],
                        'status' => 'completed',
                        'scanned_at' => now()
                    ]);

                    Log::info("IP scan completed for {$ipScan->ip_address}");
                    $successCount++;

                } catch (\Exception $e) {
                    Log::error("Failed to scan {$ipScan->ip_address}: " . $e->getMessage());
                    
                    $ipScan->update([
                        'status' => 'failed',
                        'scan_details' => "Scan failed: " . $e->getMessage()
                    ]);
                    
                    $failureCount++;
                }
            }

            Log::info("Batch processing completed: $successCount successful, $failureCount failed for batch {$this->batchId}");

            // Check if there are more IPs to process
            $remainingCount = IpScan::where('batch_id', $this->batchId)
                ->where('status', 'pending')
                ->count();

            if ($remainingCount > 0) {
                Log::info("Dispatching new batch job for remaining $remainingCount IPs in batch {$this->batchId}");
                // Dispatch another batch job for remaining IPs
                ProcessIpScanBatch::dispatch($this->batchId)->delay(now()->addSeconds(5));
            } else {
                // All IPs processed, update batch status
                $this->checkBatchCompletion($uploadBatch);
            }

        } catch (\Exception $e) {
            Log::error("Batch processing failed for batch {$this->batchId}: " . $e->getMessage());
            
            $uploadBatch = UploadBatch::where('batch_id', $this->batchId)->first();
            if ($uploadBatch) {
                $uploadBatch->update([
                    'status' => 'failed',
                    'error_message' => 'Batch processing failed: ' . $e->getMessage(),
                ]);
            }
            
            throw $e;
        }
    }

    /**
     * Check if batch is complete and update batch status accordingly.
     */
    private function checkBatchCompletion(UploadBatch $uploadBatch): void
    {
        $totalIps = IpScan::where('batch_id', $this->batchId)->count();
        $completedIps = IpScan::where('batch_id', $this->batchId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        if ($totalIps === $completedIps) {
            $failedCount = IpScan::where('batch_id', $this->batchId)
                ->where('status', 'failed')
                ->count();

            $status = $failedCount > 0 ? 'completed_with_errors' : 'completed';
            
            $uploadBatch->update([
                'status' => $status,
                'completed_at' => now()
            ]);

            Log::info("Batch {$this->batchId} completed with status: $status");
        }
    }
}
