<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpScan extends Model
{
    protected $fillable = [
        'batch_id',
        'ip_address',
        'is_online',
        'ping_time',
        'open_ports',
        'vulnerable_ports',
        'scan_details',
        'status',
        'scanned_at'
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'open_ports' => 'array',
        'vulnerable_ports' => 'array',
        'scanned_at' => 'datetime'
    ];

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class, 'batch_id', 'batch_id');
    }
}
