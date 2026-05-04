<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class AdminUser extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'admin_users';

    public $timestamps = false;

    protected $fillable = ['username', 'password_hash'];

    protected $hidden = ['password_hash'];

    // Map Laravel's expected `password` to `password_hash`
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ─── JWT ──────────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['username' => $this->username];
    }
}
