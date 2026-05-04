<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalAdNetwork extends Model
{
    protected $table = 'global_ad_networks';

    protected $fillable = [
        'name',
        'display_name',
        'logo_url',
        'description',
        'website_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order');
    }
}
