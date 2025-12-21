<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    // 1. Helper to check role
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    // 2. Scope to get only Admins
    // Usage: User::admins()->get();
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    // 3. Scope to get only App Users
    // Usage: User::storytellers()->get();
    public function scopeStorytellers($query)
    {
        return $query->where('is_admin', false);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }
}
