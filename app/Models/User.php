<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'gender', 'height_cm', 'weight_kg', 'activity_level', 'daily_goal'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mirrors the column defaults so a freshly created user is fully populated.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'google_id' => null,
        'gender' => null,
        'height_cm' => null,
        'weight_kg' => null,
        'activity_level' => 'moderate',
        'daily_goal' => 2000,
        'profile_completed_at' => null,
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
            'height_cm' => 'float',
            'weight_kg' => 'float',
            'daily_goal' => 'integer',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FoodEntry, $this>
     */
    public function foodEntries(): HasMany
    {
        return $this->hasMany(FoodEntry::class);
    }

    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }
}
