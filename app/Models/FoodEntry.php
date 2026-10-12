<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodEntry extends Model
{
    protected $fillable = [
        'name',
        'quantity',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'sugar_g',
        'sodium_mg',
        'notes',
        'source',
        'confidence',
        'verification_note',
        'breakdown',
        'consumed_on',
    ];

    protected function casts(): array
    {
        return [
            'consumed_on' => 'date',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'sugar_g' => 'float',
            'sodium_mg' => 'float',
            'breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<FoodEntry>  $query
     * @return Builder<FoodEntry>
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('consumed_on', $date);
    }
}
