@extends('layouts.app')

@section('title', 'Scan Results')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-search me-2"></i>Scan Results</h1>
                <p class="text-muted mb-0">{{ $batch->filename }} - {{ $batch->created_at->format('M j, Y H:i') }}</p>
            </div>
            <div>
                <span class="scan-status status-{{ $batch->status }} me-2">{{ ucfirst($batch->status) }}</span>
                @if($batch->status === 'completed')
                    <a href="{{ route('pentest.select', $batch->batch_id) }}" class="btn btn-danger me-2">
                        <i class="fas fa-skull-crossbones me-1"></i>Start Pentest
                    </a>
                    <a href="{{ route('pentest.sessions', $batch->batch_id) }}" class="btn btn-warning me-2">
                        <i class="fas fa-history me-1"></i>Pentest History
                    </a>
                    <a href="{{ route('scan.report', $batch->batch_id) }}" class="btn btn-info me-2">
                        <i class="fas fa-chart-bar me-1"></i>View Report
                    </a>
                    <a href="{{ route('scan.export', $batch->batch_id) }}" class="btn btn-success">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4" id="statscards">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 text-primary mb-0">{{ $batch->total_ips }}</div>
                <small class="text-muted">Total IPs</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 text-info mb-0">{{ $batch->scanned_ips }}</div>
                <small class="text-muted">Scanned</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 text-success mb-0">{{ $batch->online_ips }}</div>
                <small class="text-muted">Online</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="h2 text-danger mb-0">{{ $batch->vulnerable_ips }}</div>
                <small class="text-muted">Vulnerable</small>
            </div>
        </div>
    </div>
</div>
@php
    $batch_progress = 'style="width:'. $batch->progress .'%"';
@endphp
@if($batch->status === 'processing')
    
    <div class="card mb-4">
        <div class="card-body">
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     {{ $batch_progress }}
                     id="scan-progress">
                    {{ $batch->progress }}%
                </div>
            </div>
            <div class="text-center mt-2">
                <small class="text-muted">Scanning in progress... This page will auto-refresh.</small>
            </div>
        </div>
    </div>
@endif

<!-- IP Scan Results Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">IP Scan Results</h5>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="filter" id="filter-all" autocomplete="off" checked>
            <label class="btn btn-outline-secondary" for="filter-all">All</label>
            
            <input type="radio" class="btn-check" name="filter" id="filter-online" autocomplete="off">
            <label class="btn btn-outline-success" for="filter-online">Online</label>
            
            <input type="radio" class="btn-check" name="filter" id="filter-vulnerable" autocomplete="off">
            <label class="btn btn-outline-danger" for="filter-vulnerable">Vulnerable</label>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Online</th>
                        <th>Ping Time</th>
                        <th>Open Ports</th>
                        <th>Vulnerabilities</th>
                        <th>Scanned At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ipScans as $scan)
                        <tr class="scan-row" 
                            data-online="{{ $scan->is_online ? 'true' : 'false' }}" 
                            data-vulnerable="{{ !empty($scan->vulnerable_ports) ? 'true' : 'false' }}">
                            <td>
                                <strong>{{ $scan->ip_address }}</strong>
                            </td>
                            <td>
                                <span class="scan-status status-{{ $scan->status }}">
                                    {{ ucfirst($scan->status) }}
                                </span>
                            </td>
                            <td>
                                @if($scan->is_online)
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span class="text-success">Online</span>
                                @else
                                    <i class="fas fa-times-circle text-danger"></i>
                                    <span class="text-danger">Offline</span>
                                @endif
                            </td>
                            <td>
                                @if($scan->ping_time)
                                    {{ $scan->ping_time }}ms
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($scan->open_ports))
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($scan->open_ports as $port => $service)
                                            <span class="badge bg-info">{{ $port }}/{{ $service }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">None detected</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($scan->vulnerable_ports))
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($scan->vulnerable_ports as $port => $description)
                                            <span class="badge bg-danger" title="{{ $description }}">{{ $port }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-success">
                                        <i class="fas fa-shield-alt me-1"></i>None
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($scan->scanned_at)
                                    {{ $scan->scanned_at->format('M j, H:i') }}
                                @else
                                    <span class="text-muted">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-search mb-2"></i>
                                    <p>No scan results available.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($ipScans->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $ipScans->links() }}
    </div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-refresh for processing scans
    @if($batch->status === 'processing')
        const updateProgress = () => {
            $.get(`/scan/{{ $batch->batch_id }}/status`)
                .done(function(data) {
                    $('#scan-progress').css('width', data.progress + '%').text(data.progress + '%');
                    if (data.status === 'failed') {
                        // Show error message and stop spinner
                        $('.progress-bar').removeClass('progress-bar-animated');
                        $('.progress').after('<div class="alert alert-danger mt-3">Scan failed. Please check logs or try again.</div>');
                        return;
                    }
                    if (data.status === 'completed') {
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        setTimeout(() => {
                            location.reload();
                        }, 10000);
                    }
                })
                .fail(function() {
                    setTimeout(updateProgress, 10000);
                });
        };
        setTimeout(updateProgress, 20000);
    @endif
    
    // Filter functionality
    $('input[name="filter"]').on('change', function() {
        const filter = $(this).attr('id').replace('filter-', '');
        const rows = $('.scan-row');
        
        rows.show();
        
        if (filter === 'online') {
            rows.filter('[data-online="false"]').hide();
        } else if (filter === 'vulnerable') {
            rows.filter('[data-vulnerable="false"]').hide();
        }
    });
});
</script>
@endpush
