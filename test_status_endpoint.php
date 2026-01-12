<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UploadBatch;

// Get latest batch
$batch = UploadBatch::latest()->first();

if ($batch) {
    echo "Testing Status Endpoint Response:\n\n";
    echo "Batch ID: {$batch->batch_id}\n";
    echo "Status: {$batch->status}\n";
    echo "Progress: {$batch->progress}%\n";
    echo "Scanned IPs: {$batch->scanned_ips}\n";
    echo "Online IPs: {$batch->online_ips}\n";
    echo "Vulnerable IPs: {$batch->vulnerable_ips}\n";
    echo "Total IPs: {$batch->total_ips}\n\n";
    
    echo "JSON Response:\n";
    $response = [
        'progress' => $batch->progress,
        'status' => $batch->status,
        'scanned_ips' => $batch->scanned_ips,
        'online_ips' => $batch->online_ips,
        'vulnerable_ips' => $batch->vulnerable_ips,
        'total_ips' => $batch->total_ips
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    echo "No batches found.\n";
}
