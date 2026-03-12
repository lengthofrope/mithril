<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Represents an authenticated user of the application.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme_preference',
        'avatar_path',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'microsoft_id',
        'microsoft_email',
        'microsoft_access_token',
        'microsoft_refresh_token',
        'microsoft_token_expires_at',
        'timezone',
        'prune_after_days',
        'dashboard_upcoming_tasks',
        'dashboard_upcoming_follow_ups',
        'dashboard_upcoming_bilas',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'microsoft_access_token',
        'microsoft_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'two_factor_confirmed_at'    => 'datetime',
            'microsoft_access_token'     => 'encrypted',
            'microsoft_refresh_token'    => 'encrypted',
            'microsoft_token_expires_at' => 'datetime',
            'prune_after_days'              => 'integer',
            'dashboard_upcoming_tasks'      => 'integer',
            'dashboard_upcoming_follow_ups' => 'integer',
            'dashboard_upcoming_bilas'      => 'integer',
            'is_active'                     => 'boolean',
        ];
    }

    /**
     * Determine whether the user has two-factor authentication enabled and confirmed.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Determine whether the user has an active Microsoft connection.
     */
    public function hasMicrosoftConnection(): bool
    {
        return $this->microsoft_id !== null;
    }

    /**
     * Get the user's effective timezone, defaulting to Europe/Amsterdam.
     */
    public function getEffectiveTimezone(): string
    {
        return $this->timezone ?? 'Europe/Amsterdam';
    }

    /**
     * Get all calendar events belonging to this user.
     *
     * @return HasMany<CalendarEvent>
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
