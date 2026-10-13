<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\WaterCalculator;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'gender', 'height_cm', 'weight_kg', 'activity_level', 'daily_goal', 'daily_water_goal_ml', 'water_starts_at', 'water_ends_at', 'lorie_language'])]
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
        'daily_water_goal_ml' => null,
        'water_starts_at' => '07:00',
        'water_ends_at' => '21:00',
        'lorie_language' => 'mix',
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
            'daily_water_goal_ml' => 'integer',
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

    /**
     * @return HasMany<WaterLog, $this>
     */
    public function waterLogs(): HasMany
    {
        return $this->hasMany(WaterLog::class);
    }

    /**
     * Daily water goal in millilitres: the user's own figure if they set one,
     * otherwise the amount suggested from their weight and activity.
     */
    public function waterGoalMl(): int
    {
        return $this->daily_water_goal_ml
            ?? WaterCalculator::recommendedGoalMl($this->gender, $this->weight_kg, $this->activity_level);
    }

    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }
}
