<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    protected $fillable = [
        'type', 'period', 'user_id', 'username', 'score', 'rank', 'metadata', 'period_date'
    ];

    protected $casts = [
        'metadata' => 'array',
        'period_date' => 'date',
        'score' => 'integer',
        'rank' => 'integer',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

}
