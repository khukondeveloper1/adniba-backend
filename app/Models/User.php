<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'app_limit',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'status'    => 'boolean',
        'app_limit' => 'integer',
    ];

    // ─── JWT ──────────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'email' => $this->email,
            'role'  => 'developer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function apps(): HasMany
    {
        return $this->hasMany(App::class, 'user_id');
    }

    public function limitRequests(): HasMany
    {
        return $this->hasMany(AppLimitRequest::class, 'user_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    public function canCreateApp(): bool
    {
        return $this->apps()->count() < $this->app_limit;
    }

    public function remainingAppSlots(): int
    {
        return max(0, $this->app_limit - $this->apps()->count());
    }

    public function hasPendingLimitRequest(): bool
    {
        return $this->limitRequests()
            ->where('status', 'pending')
            ->exists();
    }
}
