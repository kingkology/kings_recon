@extends('layouts.app')

@section('title', 'Vulnerability Report')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-chart-bar me-2"></i>Vulnerability Report</h1>
                <p class="text-muted mb-0">Generated {{ now()->format('M j, Y H:i') }}</p>
            </div>
            <div class="d-print-none">
                <a href="{{ route('scan.show', $batch->batch_id) }}" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Results
                </a>
                <a href="{{ route('scan.export', $batch->batch_id) }}" class="btn btn-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </a>
                <button type="button" class="btn btn-danger" onclick="window.print()">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Executive Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Executive Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Scan Statistics</h6>
                        <ul class="list-unstyled">
                            <li><strong>Total IPs Scanned:</strong> {{ $batch->total_ips }}</li>
                            <li><strong>Online Hosts:</strong> {{ $batch->online_ips }} ({{ $batch->total_ips > 0 ? round(($batch->online_ips / $batch->total_ips) * 100, 1) : 0 }}%)</li>
                            <li><strong>Vulnerable Hosts:</strong> {{ $batch->vulnerable_ips }} ({{ $batch->online_ips > 0 ? round(($batch->vulnerable_ips / $batch->online_ips) * 100, 1) : 0 }}%)</li>
                            <li><strong>Scan Duration:</strong> {{ $batch->started_at && $batch->completed_at ? $batch->started_at->diffForHumans($batch->completed_at, true) : 'N/A' }}</li>
                        </div>
                    <div class="col-md-6">
                        <h6>Risk Assessment</h6>
                        @php
                            $riskLevel = 'Low';
                            $riskClass = 'success';
                            $vulnerabilityPercentage = $batch->online_ips > 0 ? ($batch->vulnerable_ips / $batch->online_ips) * 100 : 0;
                            
                            if ($vulnerabilityPercentage > 50) {
                                $riskLevel = 'Critical';
                                $riskClass = 'danger';
                            } elseif ($vulnerabilityPercentage > 25) {
                                $riskLevel = 'High';
                                $riskClass = 'warning';
                            } elseif ($vulnerabilityPercentage > 10) {
                                $riskLevel = 'Medium';
                                $riskClass = 'info';
                            }
                        @endphp
                        <div class="alert alert-{{ $riskClass }}">
                            <strong>Overall Risk Level: {{ $riskLevel }}</strong><br>
                            <small>{{ round($vulnerabilityPercentage, 1) }}% of online hosts have vulnerabilities</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($vulnerableIps->count() > 0)
    <!-- Vulnerability Analysis -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Vulnerability Analysis</h5>
                </div>
                <div class="card-body">
                    @php
                        $portCounts = [];
                        foreach($vulnerableIps as $ip) {
                            if (!empty($ip->vulnerable_ports)) {
                                foreach($ip->vulnerable_ports as $port => $description) {
                                    $portCounts[$port] = ($portCounts[$port] ?? 0) + 1;
                                }
                            }
                        }
                        arsort($portCounts);
                    @endphp
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Most Common Vulnerable Ports</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Port</th>
                                            <th>Count</th>
                                            <th>Risk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($portCounts, 0, 10, true) as $port => $count)
                                            <tr>
                                                <td><strong>{{ $port }}</strong></td>
                                                <td>{{ $count }}</td>
                                                <td><span class="vulnerability-{{ strtolower($portSeverityMap[$port] ?? 'LOW') }}">{{ $portSeverityMap[$port] ?? 'LOW' }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Risk Distribution</h6>
                            <div style="position:relative; min-height:220px; max-width:100%;">
                                <canvas id="riskChart" style="width:100%; max-width:400px; height:220px; max-height:300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Vulnerabilities -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Detailed Vulnerability Report</h5>
                </div>
                <div class="card-body">
                    @foreach($vulnerableIps as $ip)
                        @if(!empty($vulnerabilityReport[$ip->ip_address]))
                            <div class="mb-4 border-bottom pb-3">
                                <h6 class="text-primary">
                                    <i class="fas fa-desktop me-2"></i>{{ $ip->ip_address }}
                                    @if($ip->ping_time)
                                        <small class="text-muted">({{ $ip->ping_time }}ms)</small>
                                    @endif
                                </h6>
                                
                                <div class="row">
                                    @foreach($vulnerabilityReport[$ip->ip_address] as $vuln)
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-{{ $vuln['severity'] === 'HIGH' ? 'danger' : ($vuln['severity'] === 'MEDIUM' ? 'warning' : 'info') }}">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <strong>Port {{ $vuln['port'] }} - {{ $vuln['service'] }}</strong>
                                                        <span class="vulnerability-{{ strtolower($vuln['severity']) }}">{{ $vuln['severity'] }}</span>
                                                    </div>
                                                    <p class="text-muted small mb-2">{{ $vuln['vulnerability'] }}</p>
                                                    <div class="alert alert-{{ $vuln['severity'] === 'HIGH' ? 'danger' : ($vuln['severity'] === 'MEDIUM' ? 'warning' : 'info') }} py-2 px-3 mb-0">
                                                        <small><strong>Recommendation:</strong> {{ $vuln['recommendation'] }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Online Hosts Summary -->
@if($onlineIps->count() > 0)
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-wifi me-2"></i>Online Hosts Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Response Time</th>
                                    <th>Open Ports</th>
                                    <th>Risk Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($onlineIps as $ip)
                                    <tr>
                                        <td><strong>{{ $ip->ip_address }}</strong></td>
                                        <td>{{ $ip->ping_time }}ms</td>
                                        <td>
                                            @if(!empty($ip->open_ports))
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($ip->open_ports as $port => $service)
                                                        <span class="badge bg-secondary">{{ $port }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">None detected</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($ip->vulnerable_ports) && !empty($vulnerabilityReport[$ip->ip_address]))
                                                @php
                                                    $severityOrder = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1, 'INFO' => 0];
                                                    $maxSeverity = 'LOW';
                                                    foreach($vulnerabilityReport[$ip->ip_address] as $vuln) {
                                                        if ($severityOrder[$vuln['severity']] > $severityOrder[$maxSeverity]) {
                                                            $maxSeverity = $vuln['severity'];
                                                        }
                                                    }
                                                @endphp
                                                <span class="vulnerability-{{ strtolower($maxSeverity) }}">{{ $maxSeverity }}</span>
                                            @else
                                                <span class="text-success"><i class="fas fa-shield-alt me-1"></i>Secure</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($vulnerableIps->count() === 0 && $onlineIps->count() === 0)
    <div class="text-center py-5">
        <div class="display-1 text-muted mb-3">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h3 class="text-success">All Clear!</h3>
        <p class="text-muted">No vulnerabilities detected in the scanned IP range.</p>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    @if($vulnerableIps->count() > 0)
        // Risk distribution chart
        const ctx = document.getElementById('riskChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['High Risk', 'Medium Risk', 'Low Risk'],
                datasets: [{
                    data: [
                        {{ isset($severityCounts['HIGH']) ? (int)$severityCounts['HIGH'] : 0 }},
                        {{ isset($severityCounts['MEDIUM']) ? (int)$severityCounts['MEDIUM'] : 0 }},
                        {{ isset($severityCounts['LOW']) ? (int)$severityCounts['LOW'] : 0 }}
                    ],
                    backgroundColor: ['#dc2626', '#d97706', '#059669'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: @json(!request('pdf')),
                layout: {
                    padding: 10
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 18,
                            font: { size: 14 }
                        }
                    }
                }
            }
        });
        // For Browsershot: wait for chart to render before printing
        if (@json(request('pdf'))) {
            setTimeout(() => window.print && window.print(), 1000);
        }
    @endif
});
</script>
@endpush
