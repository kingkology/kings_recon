<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscoveredCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'ip_address',
        'service',
        'port',
        'username',
        'password',
        'access_level',
        'notes',
        'verified',
        'discovered_at'
    ];

    protected $casts = [
        'verified' => 'boolean',
        'discovered_at' => 'datetime'
    ];

    public function session()
    {
        return $this->belongsTo(PentestSession::class, 'session_id', 'session_id');
    }

    public function getAccessLevelColorAttribute()
    {
        return match($this->access_level) {
            'root' => 'danger',
            'admin' => 'warning',
            'user' => 'info',
            'guest' => 'secondary',
            default => 'light'
        };
    }
}
