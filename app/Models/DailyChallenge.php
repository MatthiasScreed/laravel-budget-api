<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DailyChallenge extends Model
{
    protected $fillable = [
        'challenge_date', 'type', 'title', 'description', 'criteria',
        'reward_xp', 'bonus_rewards', 'difficulty', 'is_global', 'is_active',
    ];

    protected $casts = [
        'challenge_date' => 'date',
        'criteria' => 'array',
        'bonus_rewards' => 'array',
        'reward_xp' => 'integer',
        'is_global' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_daily_challenges')
            ->withPivot(['status', 'progress_percentage', 'progress_data', 'started_at', 'completed_at', 'reward_claimed'])
            ->withTimestamps();
    }
}
