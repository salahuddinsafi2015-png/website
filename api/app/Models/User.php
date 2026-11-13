<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'display_name',
        'bio',
        'avatar_url',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->username)) {
                $user->username = static::uniqueUsername($user->email ?: $user->name);
            }
        });
    }

    public static function uniqueUsername(string $source): string
    {
        $localPart = Str::before($source, '@');
        $base = Str::slug($localPart, '_') ?: 'user';
        $username = $base;
        $suffix = 2;

        while (static::query()->where('username', $username)->exists()) {
            $username = "{$base}_{$suffix}";
            $suffix++;
        }

        return $username;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Display name fallback chain used across API responses.
     */
    public function getNameAttribute(): string
    {
        return $this->attributes['display_name']
            ?? $this->attributes['name']
            ?? $this->attributes['username']
            ?? '';
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }
}
