<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    protected $table = 'ad_events';

    // Table has only created_at, not updated_at
    public $timestamps = false;

    protected $fillable = [
        'app_id',
        'network',
        'ad_type',
        'placement',
        'event_type',
        'created_at',
    ];

    // Supported event types
    public const EVENT_REQUEST    = 'request';
    public const EVENT_LOAD       = 'load';
    public const EVENT_IMPRESSION = 'impression';
    public const EVENT_CLICK      = 'click';
    public const EVENT_FAIL       = 'fail';

    public const EVENT_TYPES = [
        self::EVENT_REQUEST,
        self::EVENT_LOAD,
        self::EVENT_IMPRESSION,
        self::EVENT_CLICK,
        self::EVENT_FAIL,
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
