<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\UploadBatch;
use App\Models\IpScan;

echo "=== COMPLETE BATCH STATISTICS BACKFILL ===\n\n";

$batches = UploadBatch::orderBy('created_at', 'asc')->get();
$updated = 0;
$skipped = 0;

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
    
    // Always update - force fresh stats
    $batch->update([
        'scanned_ips' => $totalScanned,
        'online_ips' => $online,
        'vulnerable_ips' => $vulnerable
    ]);
    
    echo "✅ Updated: {$batch->batch_id}\n";
    echo "   Filename: {$batch->filename}\n";
    echo "   Scanned: $totalScanned | Online: $online | Vulnerable: $vulnerable\n\n";
    
    $updated++;
}

echo "=== RESULT ===\n";
echo "Updated $updated batches with statistics.\n";
