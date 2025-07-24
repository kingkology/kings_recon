<?php
// File: app/Http/Controllers/IpValidatorController.php

namespace App\Http\Controllers;

use App\Imports\IpImport;
use App\Jobs\ProcessIpScan;
use App\Models\IpScan;
use App\Models\UploadBatch;
use App\Services\IpScanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
// Removed PDF and Browsershot imports

use Illuminate\Support\Str;

class IpValidatorController extends Controller
{
    public function index(): View
    {
        $batches = UploadBatch::orderBy('created_at', 'desc')->paginate(10);
        return view('ip-validator.index', compact('batches'));
    }

    public function upload(): View
    {
        return view('ip-validator.upload');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:txt,csv,xlsx,xls|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            
            // Create upload batch
            $batch = UploadBatch::create([
                'filename' => $filename,
                'total_ips' => 0,
                'status' => 'pending'
            ]);

            // Process file based on type
            if (in_array($file->getClientOriginalExtension(), ['csv', 'xlsx', 'xls'])) {
                $this->processExcelFile($file, $batch);
            } else {
                $this->processTextFile($file, $batch);
            }

            // Update total IPs count
            $totalIps = IpScan::where('batch_id', $batch->batch_id)->count();
            $batch->update([
                'total_ips' => $totalIps,
                'status' => 'processing',
                'started_at' => now()
            ]);

            // Dispatch jobs for each IP
            $this->dispatchScanJobs($batch->batch_id);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully. Scanning started.',
                'batch_id' => $batch->batch_id,
                'total_ips' => $totalIps
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processExcelFile($file, UploadBatch $batch): void
    {
        Excel::import(new IpImport($batch->batch_id), $file);
    }

    private function processTextFile($file, UploadBatch $batch): void
    {
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);
        
        $ips = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && IpScanService::validateIpAddress($line)) {
                $ips[] = [
                    'batch_id' => $batch->batch_id,
                    'ip_address' => $line,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        if (!empty($ips)) {
            IpScan::insert($ips);
        }
    }

    private function dispatchScanJobs(string $batchId): void
    {
        $ipScans = IpScan::where('batch_id', $batchId)->where('status', 'pending')->get();
        
        foreach ($ipScans as $ipScan) {
            ProcessIpScan::dispatch($ipScan);
        }
    }

    public function show(string $batchId): View
    {
        $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
        $ipScans = IpScan::where('batch_id', $batchId)
            ->orderByRaw("FIELD(status, 'completed', 'scanning', 'pending')")
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        return view('ip-validator.show', compact('batch', 'ipScans'));
    }

    public function status(string $batchId): JsonResponse
    {
        $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
        $failedScan = $batch->ipScans()->where('status', 'failed')->first();
        $status = $batch->status;
        if ($failedScan) {
            $status = 'failed';
        }
        return response()->json([
            'batch' => $batch,
            'progress' => $batch->progress,
            'status' => $status
        ]);
    }

    public function report(string $batchId): View
    {
        $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
        
        // Get vulnerability statistics
        $vulnerableIps = IpScan::where('batch_id', $batchId)
            ->whereNotNull('vulnerable_ports')
            ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')
            ->get();
            
        $onlineIps = IpScan::where('batch_id', $batchId)
            ->where('is_online', true)
            ->get();

        // Generate vulnerability report and severity maps
        $scanService = new IpScanService();
        $vulnerabilityReport = [];
        $portSeverityMap = [];
        $severityCounts = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];

        foreach ($vulnerableIps as $ip) {
            if (!empty($ip->vulnerable_ports)) {
                $vulnerabilityReport[$ip->ip_address] = $scanService->getVulnerabilityReport($ip->vulnerable_ports);
                foreach ($vulnerabilityReport[$ip->ip_address] as $vuln) {
                    $portSeverityMap[$vuln['port']] = $vuln['severity'];
                    if (isset($severityCounts[$vuln['severity']])) {
                        $severityCounts[$vuln['severity']]++;
                    }
                }
            }
        }

        return view('ip-validator.report', compact('batch', 'vulnerableIps', 'onlineIps', 'vulnerabilityReport', 'portSeverityMap', 'severityCounts'));
    }

    public function exportReport(string $batchId)
    {
        $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
        $ipScans = IpScan::where('batch_id', $batchId)->get();
        
        $filename = "ip_scan_report_{$batchId}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() use ($ipScans) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'IP Address',
                'Status',
                'Online',
                'Ping Time (ms)',
                'Open Ports',
                'Vulnerable Ports',
                'Vulnerability Count',
                'Scanned At'
            ]);
            
            foreach ($ipScans as $scan) {
                fputcsv($file, [
                    $scan->ip_address,
                    $scan->status,
                    $scan->is_online ? 'Yes' : 'No',
                    $scan->ping_time,
                    implode(', ', array_keys($scan->open_ports ?? [])),
                    implode(', ', array_keys($scan->vulnerable_ports ?? [])),
                    count($scan->vulnerable_ports ?? []),
                    $scan->scanned_at ? $scan->scanned_at->format('Y-m-d H:i:s') : ''
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }


    // public function exportReportPdf(string $batchId)
    // {
    //     $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
    //     $vulnerableIps = IpScan::where('batch_id', $batchId)
    //         ->whereNotNull('vulnerable_ports')
    //         ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')
    //         ->get();
    //     $onlineIps = IpScan::where('batch_id', $batchId)
    //         ->where('is_online', true)
    //         ->get();
    //     $scanService = new IpScanService();
    //     $vulnerabilityReport = [];
    //     foreach ($vulnerableIps as $ip) {
    //         if (!empty($ip->vulnerable_ports)) {
    //             $vulnerabilityReport[$ip->ip_address] = $scanService->getVulnerabilityReport($ip->vulnerable_ports);
    //         }
    //     }
    //     $pdf = Pdf::loadView('ip-validator.report-pdf', compact('batch', 'vulnerableIps', 'onlineIps', 'vulnerabilityReport'));
    //     $filename = 'ip_scan_report_' . $batchId . '.pdf';
    //     return $pdf->download($filename);
    // }


    // Removed exportReportPdf method. Use browser print instead.

    // public function exportReportPdf(string $batchId)
    // {
    //     $batch = UploadBatch::where('batch_id', $batchId)->firstOrFail();
    //     $vulnerableIps = IpScan::where('batch_id', $batchId)
    //         ->whereNotNull('vulnerable_ports')
    //         ->whereRaw('JSON_LENGTH(vulnerable_ports) > 0')
    //         ->get();
    //     $onlineIps = IpScan::where('batch_id', $batchId)
    //         ->where('is_online', true)
    //         ->get();
    //     $scanService = new IpScanService();
    //     $vulnerabilityReport = [];
    //     foreach ($vulnerableIps as $ip) {
    //         if (!empty($ip->vulnerable_ports)) {
    //             $vulnerabilityReport[$ip->ip_address] = $scanService->getVulnerabilityReport($ip->vulnerable_ports);
    //         }
    //     }
    //     $pdf = Pdf::loadView('ip-validator.report-pdf', compact('batch', 'vulnerableIps', 'onlineIps', 'vulnerabilityReport'));
    //     $filename = 'ip_scan_report_' . $batchId . '.pdf';
    //     return $pdf->download($filename);
    // }

}
