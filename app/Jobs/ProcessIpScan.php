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

class ProcessIpScan implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    private IpScan $ipScan;

    /**
     * Create a new job instance.
     */
    public function __construct(IpScan $ipScan)
    {
        $this->ipScan = $ipScan;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $scanService = null;
            try {
                $scanService = new IpScanService();
            } catch (\Exception $e) {
                $this->ipScan->update([
                    'status' => 'failed',
                    'scan_details' => "IpScanService init failed: " . $e->getMessage()
                ]);
                $batch = $this->ipScan->uploadBatch;
                if ($batch) {
                    $batch->update([
                        'status' => 'failed',
                        'error_message' => 'IpScanService init failed: ' . $e->getMessage(),
                    ]);
                }
                throw $e;
            }
            // Update status to scanning
            $this->ipScan->update(['status' => 'scanning']);
            $result = null;
            try {
                $result = $scanService->performFullScan($this->ipScan->ip_address);
            } catch (\Exception $e) {
                $this->ipScan->update([
                    'status' => 'failed',
                    'scan_details' => "performFullScan failed: " . $e->getMessage()
                ]);
                $batch = $this->ipScan->uploadBatch;
                if ($batch) {
                    $batch->update([
                        'status' => 'failed',
                        'error_message' => 'performFullScan failed: ' . $e->getMessage(),
                    ]);
                }
                throw $e;
            }
            // Update the IP scan record with results
            try {
                $this->ipScan->update([
                    'is_online' => $result['is_online'],
                    'ping_time' => $result['ping_time'],
                    'open_ports' => $result['open_ports'],
                    'vulnerable_ports' => $result['vulnerable_ports'],
                    'scan_details' => $result['scan_details'],
                    'status' => 'completed',
                    'scanned_at' => now()
                ]);
            } catch (\Exception $e) {
                $this->ipScan->update([
                    'status' => 'failed',
                    'scan_details' => "Result update failed: " . $e->getMessage()
                ]);
                $batch = $this->ipScan->uploadBatch;
                if ($batch) {
                    $batch->update([
                        'status' => 'failed',
                        'error_message' => 'Result update failed: ' . $e->getMessage(),
                    ]);
                }
                throw $e;
            }
            // Update batch statistics
            try {
                $this->updateBatchStatistics();
            } catch (\Exception $e) {
                $this->ipScan->update([
                    'status' => 'failed',
                    'scan_details' => "Batch statistics update failed: " . $e->getMessage()
                ]);
                $batch = $this->ipScan->uploadBatch;
                if ($batch) {
                    $batch->update([
                        'status' => 'failed',
                        'error_message' => 'Batch statistics update failed: ' . $e->getMessage(),
                    ]);
                }
                throw $e;
            }
            Log::info("IP scan completed for {$this->ipScan->ip_address}");
        } catch (\Exception $e) {
            Log::error("IP scan failed for {$this->ipScan->ip_address}: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateBatchStatistics(): void
    {
        $batch = UploadBatch::where('batch_id', $this->ipScan->batch_id)->first();
        
        if ($batch) {
            $scannedCount = IpScan::where('batch_id', $this->ipScan->batch_id)
                ->where('status', 'completed')
                ->count();
                
            $onlineCount = IpScan::where('batch_id', $this->ipScan->batch_id)
                ->where('is_online', true)
                ->count();
                
            $vulnerableCount = IpScan::where('batch_id', $this->ipScan->batch_id)
                ->whereNotNull('vulnerable_ports')
                ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')
                ->count();
            
            $batch->update([
                'scanned_ips' => $scannedCount,
                'online_ips' => $onlineCount,
                'vulnerable_ips' => $vulnerableCount
            ]);
            
            // Check if batch is completed
            if ($scannedCount >= $batch->total_ips) {
                $batch->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }
        }
    }
}
