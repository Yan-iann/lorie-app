<?php

namespace App\Models;

use App\Support\WaterCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterLog extends Model
{
    protected $fillable = [
        'amount_ml',
        'container',
        'consumed_on',
    ];

    protected function casts(): array
    {
        return [
            'amount_ml' => 'integer',
            'consumed_on' => 'date',
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
     * @param  Builder<WaterLog>  $query
     * @return Builder<WaterLog>
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('consumed_on', $date);
    }

    /**
     * The container's display name, e.g. "Medium cup".
     */
    public function containerLabel(): string
    {
        return WaterCalculator::CONTAINERS[$this->container]['label'] ?? 'Drink';
    }
}
