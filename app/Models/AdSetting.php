<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSetting extends Model
{
    protected $table = 'ad_settings';

    public $timestamps = false;

    protected $fillable = [
        'app_id',
        'ad_type',
        'placement',
        'fallback_enabled',
        'network_id',
    ];

    protected $casts = ['fallback_enabled' => 'boolean'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class, 'network_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isFallbackMode(): bool
    {
        return (bool) $this->fallback_enabled;
    }

    public function isForceMode(): bool
    {
        return !$this->fallback_enabled && $this->network_id !== null;
    }
}
