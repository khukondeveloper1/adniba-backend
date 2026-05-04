<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class App extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'apps';

    protected $fillable = [
        'user_id',
        'name',
        'package_name',
        'app_logo',
        'play_store_url',
        'app_store_url',
        'api_key',
        'status',
        'app_status',
        'global_ad_enabled',
        'is_suspended',
        'suspension_reason',
        'suspended_at',
    ];

    protected $appends = [
        'app_status',
        'is_suspended',
    ];

    protected $casts = [
        'global_ad_enabled' => 'boolean',
        'suspended_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adNetworks(): HasMany
    {
        return $this->hasMany(AdNetwork::class, 'app_id');
    }

    public function adUnits(): HasMany
    {
        return $this->hasMany(AdUnit::class, 'app_id');
    }

    public function adSettings(): HasMany
    {
        return $this->hasMany(AdSetting::class, 'app_id');
    }

    public function adEvents(): HasMany
    {
        return $this->hasMany(AdEvent::class, 'app_id');
    }

    public function appEvents(): HasMany
    {
        return $this->hasMany(AppEvent::class, 'app_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeNotSuspended($query)
    {
        return $query->where('status', '!=', self::STATUS_SUSPENDED);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasAdsEnabled(): bool
    {
        return (bool) $this->global_ad_enabled;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getAppStatusAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function setAppStatusAttribute(mixed $value): void
    {
        $this->attributes['status'] = filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? self::STATUS_ACTIVE
            : self::STATUS_INACTIVE;
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function setIsSuspendedAttribute(mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->attributes['status'] = self::STATUS_SUSPENDED;
            return;
        }

        if (($this->attributes['status'] ?? null) === self::STATUS_SUSPENDED) {
            $this->attributes['status'] = self::STATUS_ACTIVE;
        }
    }
}
