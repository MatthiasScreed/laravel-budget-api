<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GamingReward extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'icon', 'color', 'rarity',
        'criteria', 'reward_data', 'is_active', 'is_repeatable',
        'available_from', 'available_until',
    ];

    protected $casts = [
        'criteria' => 'array',
        'reward_data' => 'array',
        'is_active' => 'boolean',
        'is_repeatable' => 'boolean',
        'available_from' => 'date',
        'available_until' => 'date',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_rewards')
            ->withPivot(['earned_at', 'is_equipped', 'metadata'])
            ->withTimestamps();
    }
}
