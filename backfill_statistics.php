<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\UploadBatch;
use App\Models\IpScan;

echo "=== BATCH STATISTICS BACKFILL ===\n\n";

$batches = UploadBatch::all();
$updated = 0;

foreach ($batches as $batch) {
    $totalScanned = IpScan::where('batch_id', $batch->batch_id)
        ->whereIn('status', ['completed', 'failed'])
        ->count();
    
    $online = IpScan::where('batch_id', $batch->batch_id)
        ->where('is_online', true)
        ->count();
    
    $vulnerable = IpScan::where('batch_id', $batch->batch_id)
        ->where('is_online', true)
        ->whereNotNull('vulnerable_ports')
        ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')
        ->count();
    
    // Check if update is needed
    if ($batch->scanned_ips !== $totalScanned || $batch->online_ips !== $online || $batch->vulnerable_ips !== $vulnerable) {
        $batch->update([
            'scanned_ips' => $totalScanned,
            'online_ips' => $online,
            'vulnerable_ips' => $vulnerable
        ]);
        
        echo "✅ Updated batch {$batch->batch_id}\n";
        echo "   - Scanned: {$totalScanned}\n";
        echo "   - Online: {$online}\n";
        echo "   - Vulnerable: {$vulnerable}\n\n";
        
        $updated++;
    }
}

echo "\n=== RESULT ===\n";
echo "Updated $updated batches with statistics.\n";

// Verify the latest batch
echo "\nVerifying latest batch...\n";
$latest = UploadBatch::latest()->first();
if ($latest) {
    echo "Batch: {$latest->batch_id}\n";
    echo "- Scanned IPs: {$latest->scanned_ips}\n";
    echo "- Online IPs: {$latest->online_ips}\n";
    echo "- Vulnerable IPs: {$latest->vulnerable_ips}\n";
}
