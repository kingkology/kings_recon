@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt me-2"></i>IP Validation Dashboard</h1>
            <a href="{{ route('upload') }}" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i>Upload New File
            </a>
        </div>
    </div>
</div>

@if($batches->count() > 0)
    <div class="row">
        @foreach($batches as $batch)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-hover h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ $batch->filename }}</h6>
                        <span class="scan-status status-{{ $batch->status }}">
                            {{ ucfirst($batch->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="h4 mb-0 text-primary">{{ $batch->total_ips }}</div>
                                <small class="text-muted">Total IPs</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 mb-0 text-success">{{ $batch->online_ips }}</div>
                                <small class="text-muted">Online</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 mb-0 text-danger">{{ $batch->vulnerable_ips }}</div>
                                <small class="text-muted">Vulnerable</small>
                            </div>
                        </div>
                        
                        @php
                            $batch_status = 'style="width:'. $batch->progress .'%"';
                        @endphp
                        @if($batch->status === 'processing')
                        
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     {{ $batch_status }}
                                     data-batch-id="{{ $batch->batch_id }}">
                                    {{ $batch->progress }}%
                                </div>
                            </div>
                        @endif
                        
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Created: {{ $batch->created_at->format('M j, Y H:i') }}</span>
                            @if($batch->completed_at)
                                <span>Completed: {{ $batch->completed_at->format('M j, Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100" role="group">
                            <a href="{{ route('scan.show', $batch->batch_id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                            @if($batch->status === 'completed')
                                <a href="{{ route('scan.report', $batch->batch_id) }}" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-chart-bar me-1"></i>Report
                                </a>
                                <a href="{{ route('scan.export', $batch->batch_id) }}" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-download me-1"></i>Export
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center">
        {{ $batches->links() }}
    </div>
@else
    <div class="text-center py-5">
        <div class="display-1 text-muted mb-3">
            <i class="fas fa-network-wired"></i>
        </div>
        <h3 class="text-muted">No IP scans yet</h3>
        <p class="text-muted mb-4">Upload a file containing IP addresses to get started with vulnerability scanning.</p>
        <a href="{{ route('upload') }}" class="btn btn-primary btn-lg">
            <i class="fas fa-upload me-2"></i>Upload Your First File
        </a>
    </div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-refresh progress for processing batches
    $('.progress-bar[data-batch-id]').each(function() {
        const batchId = $(this).data('batch-id');
        const progressBar = $(this);
        
        const updateProgress = () => {
            $.get(`/scan/${batchId}/status`)
                .done(function(data) {
                    progressBar.css('width', data.progress + '%').text(data.progress + '%');
                    
                    if (data.status === 'completed' || data.status === 'failed') {
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        setTimeout(updateProgress, 5000);
                    }
                })
                .fail(function() {
                    setTimeout(updateProgress, 10000);
                });
        };
        
        setTimeout(updateProgress, 2000);
    });
});
</script>
@endpush
