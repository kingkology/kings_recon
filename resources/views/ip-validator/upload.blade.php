@extends('layouts.app')

@section('title', 'Upload File')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-upload me-2"></i>Upload IP List File</h4>
            </div>
            <div class="card-body">
                <div id="upload-form">
                    <form id="file-upload-form" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label for="file" class="form-label">Select File</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="file" 
                                   name="file" 
                                   accept=".txt,.csv,.xlsx,.xls" 
                                   required>
                            <div class="form-text">
                                Supported formats: TXT, CSV, XLSX, XLS (Max: 10MB)
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>File Format Requirements:</h6>
                            <ul class="mb-0">
                                <li><strong>Text files (.txt):</strong> One IP address per line</li>
                                <li><strong>CSV/Excel files:</strong> IP addresses in a column named "ip", "ip_address", "address", or "host"</li>
                                <li><strong>Supported IP formats:</strong> IPv4 addresses (e.g., 192.168.1.1)</li>
                                <li><strong>Private/Reserved IPs:</strong> Will be filtered out automatically</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="upload-btn">
                                <i class="fas fa-upload me-2"></i>Upload and Start Scanning
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="upload-progress" style="display: none;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h5>Processing file...</h5>
                        <p class="text-muted">Please wait while we validate and queue your IP addresses for scanning.</p>
                    </div>
                </div>
                
                <div id="upload-success" style="display: none;">
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>Upload Successful!</h5>
                        <p class="mb-2">Your file has been processed and scanning has started.</p>
                        <div id="upload-details"></div>
                        <div class="mt-3">
                            <a href="#" id="view-scan-link" class="btn btn-primary me-2">
                                <i class="fas fa-eye me-1"></i>View Scan Progress
                            </a>
                            <a href="{{ route('upload') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-upload me-1"></i>Upload Another File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>What We Scan For</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-wifi me-2 text-success"></i>Connectivity Check</h6>
                        <ul class="list-unstyled ms-3">
                            <li><i class="fas fa-check text-success me-2"></i>ICMP ping test</li>
                            <li><i class="fas fa-check text-success me-2"></i>Response time measurement</li>
                        </ul>
                        
                        <h6 class="mt-3"><i class="fas fa-door-open me-2 text-info"></i>Port Scanning</h6>
                        <ul class="list-unstyled ms-3">
                            <li><i class="fas fa-check text-success me-2"></i>Common service ports</li>
                            <li><i class="fas fa-check text-success me-2"></i>Database ports</li>
                            <li><i class="fas fa-check text-success me-2"></i>Remote access ports</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Vulnerability Assessment</h6>
                        <ul class="list-unstyled ms-3">
                            <li><span class="vulnerability-high">HIGH RISK:</span> Telnet, SMB, RDP</li>
                            <li><span class="vulnerability-medium">MEDIUM RISK:</span> FTP, SSH, Database</li>
                            <li><span class="vulnerability-low">LOW RISK:</span> HTTP services</li>
                        </ul>
                        
                        <h6 class="mt-3"><i class="fas fa-file-alt me-2 text-primary"></i>Report Features</h6>
                        <ul class="list-unstyled ms-3">
                            <li><i class="fas fa-check text-success me-2"></i>Detailed vulnerability analysis</li>
                            <li><i class="fas fa-check text-success me-2"></i>Security recommendations</li>
                            <li><i class="fas fa-check text-success me-2"></i>CSV export capability</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#file-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show progress, hide form
        $('#upload-form').hide();
        $('#upload-progress').show();
        
        $.ajax({
            url: '{{ route("upload.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#upload-progress').hide();
                
                if (response.success) {
                    $('#upload-details').html(`
                        <strong>Batch ID:</strong> ${response.batch_id}<br>
                        <strong>Total IPs:</strong> ${response.total_ips}
                    `);
                    
                    $('#view-scan-link').attr('href', `/scan/${response.batch_id}`);
                    $('#upload-success').show();
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr) {
                $('#upload-progress').hide();
                $('#upload-form').show();
                
                let errorMessage = 'An error occurred while uploading the file.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showError(errorMessage);
            }
        });
    });
    
    function showError(message) {
        const alertHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('.container').prepend(alertHtml);
    }
    
    // File input change handler
    $('#file').on('change', function() {
        const file = this.files[0];
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            if (fileSize > 10) {
                alert('File size exceeds 10MB limit. Please choose a smaller file.');
                this.value = '';
            }
        }
    });
});
</script>
@endpush
