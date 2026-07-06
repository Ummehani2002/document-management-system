<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'azure_id',
        'azure_access_token',
        'azure_refresh_token',
        'azure_token_expires_at',
        'azure_mail_consent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'azure_access_token',
        'azure_refresh_token',
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
            'azure_token_expires_at' => 'datetime',
            'azure_mail_consent_at' => 'datetime',
            'azure_access_token' => 'encrypted',
            'azure_refresh_token' => 'encrypted',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function entityAccess(): HasMany
    {
        return $this->hasMany(UserEntityAccess::class);
    }

    public function folderAccess(): HasMany
    {
        return $this->hasMany(UserFolderAccess::class);
    }

    public function accessibleEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'user_entity_access');
    }
}
