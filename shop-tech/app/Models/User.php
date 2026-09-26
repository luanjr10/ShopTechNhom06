<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'role',
    'password',
    'google_id',
    'google_avatar',
    'avatar',
])]
#[Hidden(['password', 'remember_token', 'token_version'])]
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $appends = ['avatar_url', 'has_password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_version' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(EmployeePermission::class);
    }

    public function hasModulePermission(string $module, string $ability): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if ($this->role !== 'employee') {
            return false;
        }

        $permission = $this->relationLoaded('permissions')
            ? $this->permissions->firstWhere('module', $module)
            : $this->permissions()->where('module', $module)->first();

        return (bool) ($permission?->{"can_{$ability}"} ?? false);
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->avatar) {
                return Storage::disk('public')->url($this->avatar);
            }

            return $this->google_avatar ?: null;
        });
    }

    protected function hasPassword(): Attribute
    {
        return Attribute::get(fn (): bool => ! is_null($this->password));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'tv' => (int) $this->token_version,
        ];
    }
}
