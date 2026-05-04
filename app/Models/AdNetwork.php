<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdNetwork extends Model
{
    protected $table = 'ad_networks';

    public $timestamps = false;

    protected $fillable = ['app_id', 'global_id', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    protected $appends = ['name', 'display_name'];

    // Supported network slugs
    public const ADMOB  = 'admob';
    public const META   = 'meta';
    public const UNITY  = 'unity';

    public const SUPPORTED = [self::ADMOB, self::META, self::UNITY];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }

    public function global(): BelongsTo
    {
        return $this->belongsTo(GlobalAdNetwork::class, 'global_id');
    }

    public function getNameAttribute()
    {
        return $this->global?->name;
    }

    public function getDisplayNameAttribute()
    {
        return $this->global?->display_name;
    }

    public function adUnits(): HasMany
    {
        return $this->hasMany(AdUnit::class, 'network_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('enabled', 1);
    }
}
