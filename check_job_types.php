<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== JOB QUEUE ANALYSIS ===\n\n";

// Get a sample of jobs to see what type they are
$jobs = DB::table('jobs')->select('payload')->limit(3)->get();

foreach ($jobs as $job) {
    $payload = json_decode($job->payload, true);
    echo "Job Type: " . ($payload['data']['commandName'] ?? $payload['displayName'] ?? 'Unknown') . "\n";
    echo "Full: " . substr($job->payload, 0, 200) . "...\n\n";
}

// Count job types
$allJobs = DB::table('jobs')->select('payload')->get();
$jobTypes = [];

foreach ($allJobs as $job) {
    $payload = json_decode($job->payload, true);
    $type = $payload['data']['commandName'] ?? $payload['displayName'] ?? 'Unknown';
    $jobTypes[$type] = ($jobTypes[$type] ?? 0) + 1;
}

echo "Job Type Summary:\n";
foreach ($jobTypes as $type => $count) {
    echo "- $type: $count\n";
}
