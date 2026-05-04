<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdUnit extends Model
{
    protected $table = 'ad_units';

    protected $fillable = [
        'app_id',
        'network_id',
        'ad_type',
        'placement',
        'unit_id',
        'priority',
        'enabled',
    ];

    protected $casts = ['enabled' => 'boolean'];

    // Supported ad types
    public const TYPE_BANNER        = 'banner';
    public const TYPE_INTERSTITIAL  = 'interstitial';
    public const TYPE_REWARDED      = 'rewarded';
    public const TYPE_NATIVE        = 'native';
    public const TYPE_APP_OPEN      = 'app_open';

    public const AD_TYPES = [
        self::TYPE_BANNER,
        self::TYPE_INTERSTITIAL,
        self::TYPE_REWARDED,
        self::TYPE_NATIVE,
        self::TYPE_APP_OPEN,
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class, 'network_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('ad_units.enabled', 1);
    }

    public function scopeForPlacement($query, int $appId, string $adType, string $placement)
    {
        return $query
            ->where('ad_units.app_id', $appId)
            ->where('ad_units.ad_type', $adType)
            ->where('ad_units.placement', $placement);
    }
}
