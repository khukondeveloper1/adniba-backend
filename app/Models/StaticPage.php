<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaticPage extends Model
{
    protected $table = 'static_pages';

    protected $fillable = [
        'key',
        'title',
        'content_html',
        'is_published',
        'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public const KEYS = [
        'about',
        'privacy_policy',
        'terms_conditions',
        'contact',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }
}
