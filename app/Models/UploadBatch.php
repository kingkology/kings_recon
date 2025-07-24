<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UploadBatch extends Model
{
    protected $fillable = [
        'batch_id',
        'filename',
        'total_ips',
        'scanned_ips',
        'online_ips',
        'vulnerable_ips',
        'status',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($batch) {
            if (empty($batch->batch_id)) {
                $batch->batch_id = Str::uuid();
            }
        });
    }

    public function ipScans(): HasMany
    {
        return $this->hasMany(IpScan::class, 'batch_id', 'batch_id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_ips === 0) {
            return 0;
        }
        return round(($this->scanned_ips / $this->total_ips) * 100);
    }
}
