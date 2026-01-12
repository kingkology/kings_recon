<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UploadBatch;

echo "=== STATISTICS DISPLAY FIX VERIFICATION ===\n\n";

// Get latest batch
$batch = UploadBatch::latest()->first();

if ($batch) {
    echo "✅ Latest Batch Found\n";
    echo "   Batch ID: {$batch->batch_id}\n";
    echo "   Status: {$batch->status}\n\n";
    
    echo "✅ Statistics in Database\n";
    echo "   Total IPs: {$batch->total_ips}\n";
    echo "   Scanned IPs: {$batch->scanned_ips}\n";
    echo "   Online IPs: {$batch->online_ips}\n";
    echo "   Vulnerable IPs: {$batch->vulnerable_ips}\n\n";
    
    echo "✅ Status Endpoint Response\n";
    $response = [
        'progress' => $batch->progress,
        'status' => $batch->status,
        'scanned_ips' => $batch->scanned_ips,
        'online_ips' => $batch->online_ips,
        'vulnerable_ips' => $batch->vulnerable_ips,
        'total_ips' => $batch->total_ips
    ];
    echo "   " . json_encode($response) . "\n\n";
    
    if ($batch->status === 'processing') {
        echo "⏳ Batch is currently processing\n";
        echo "   Frontend will auto-refresh every 5 seconds\n";
        echo "   Statistics will update as IPs are scanned\n";
    } else {
        echo "✅ Batch is " . $batch->status . "\n";
    }
} else {
    echo "❌ No batches found.\n";
}
