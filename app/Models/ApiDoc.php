<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiDoc extends Model
{
    protected $table = 'api_docs';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'content_html',
        'sort_order',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public const CATEGORIES = [
        'general',
        'authentication',
        'endpoints',
        'examples',
        'sdks',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }
}
