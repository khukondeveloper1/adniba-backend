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
        'phone',
        'company',
        'website',
        'avatar',
        'role',
        'status',
        'deactivation_reason',
        'deactivated_at',
        'app_limit',
        'email_verified_at',
        'email_verification_code',
        'email_verification_expires_at',
        'password_reset_token',
        'password_reset_expires_at',
    ];

    protected $hidden = ['password', 'remember_token', 'password_reset_token', 'email_verification_code'];

    protected $casts = [
        'status'            => 'boolean',
        'app_limit'         => 'integer',
        'email_verified_at' => 'datetime',
        'deactivated_at'    => 'datetime',
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

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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
