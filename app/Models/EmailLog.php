<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'to_email',
        'subject',
        'type',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public const TYPE_LIMIT_APPROVED = 'limit_approved';
    public const TYPE_LIMIT_REJECTED = 'limit_rejected';
    public const TYPE_ANNOUNCEMENT   = 'announcement';
    public const TYPE_CUSTOM         = 'custom';
    public const TYPE_WELCOME        = 'welcome';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
