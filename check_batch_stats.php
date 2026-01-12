<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\UploadBatch;
use App\Models\IpScan;

echo "=== BATCH STATISTICS ===\n\n";

// Get the latest batch
$batch = UploadBatch::latest()->first();

if ($batch) {
    echo "Latest Batch: {$batch->batch_id}\n";
    echo "Filename: {$batch->filename}\n";
    echo "Status: {$batch->status}\n";
    echo "Total IPs: {$batch->total_ips}\n\n";
    
    echo "Database Statistics:\n";
    echo "- Scanned IPs: {$batch->scanned_ips}\n";
    echo "- Online IPs: {$batch->online_ips}\n";
    echo "- Vulnerable IPs: {$batch->vulnerable_ips}\n\n";
    
    echo "Calculated from IpScans:\n";
    $totalScanned = IpScan::where('batch_id', $batch->batch_id)
        ->whereIn('status', ['completed', 'failed'])->count();
    $online = IpScan::where('batch_id', $batch->batch_id)
        ->where('is_online', true)->count();
    $vulnerable = IpScan::where('batch_id', $batch->batch_id)
        ->where('is_online', true)
        ->whereNotNull('vulnerable_ports')
        ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')->count();
    
    echo "- Scanned: $totalScanned\n";
    echo "- Online: $online\n";
    echo "- Vulnerable: $vulnerable\n\n";
    
    if ($batch->scanned_ips !== $totalScanned || $batch->online_ips !== $online || $batch->vulnerable_ips !== $vulnerable) {
        echo "⚠️  Statistics are out of sync! Need to update batch record.\n";
    } else {
        echo "✅ Statistics are up to date!\n";
    }
} else {
    echo "No batches found.\n";
}
