<?php

namespace App\Imports;

use App\Models\IpScan;
use App\Models\UploadBatch;
use App\Services\IpScanService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class IpImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    private string $batchId;
    private UploadBatch $uploadBatch;

    public function __construct(string $batchId)
    {
        $this->batchId = $batchId;
        $this->uploadBatch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
    }

    public function model(array $row)
    {
        // Try to find IP address in different possible column names
        $ip = $row['ip'] ?? $row['ip_address'] ?? $row['address'] ?? $row['host'] ?? null;
        
        // If no named column found, try the first column
        if (!$ip && !empty($row)) {
            $ip = reset($row);
        }
        
        // Clean up the IP address
        $ip = trim($ip);
        
        // Validate IP address
        if (!IpScanService::validateIpAddress($ip)) {
            return null; // Skip invalid IPs
        }

        return new IpScan([
            'batch_id' => $this->batchId,
            'ip_address' => $ip,
            'status' => 'pending'
        ]);
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }
}
