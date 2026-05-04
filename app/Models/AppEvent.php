<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'app_id',
        'event_type',
        'from_status',
        'to_status',
        'reason',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }
}
