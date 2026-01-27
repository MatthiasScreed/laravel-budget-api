<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamingEvent extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'multiplier',
        'conditions', 'rewards', 'start_at', 'end_at', 'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'rewards' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
        'multiplier' => 'decimal:2',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('is_active', true)
            ->where('start_at', '>', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Méthodes utilitaires
    public function isActive(): bool
    {
        return $this->is_active &&
            $this->start_at <= now() &&
            $this->end_at >= now();
    }

    public function getTimeRemaining(): ?int
    {
        if (! $this->isActive()) {
            return null;
        }

        return $this->end_at->diffInSeconds(now());
    }

    public function applyMultiplier(int $baseXp): int
    {
        if (! $this->isActive()) {
            return $baseXp;
        }

        return (int) round($baseXp * $this->multiplier);
    }

    // Events prédéfinis
    public static function createDoubleXpEvent(int $durationHours = 2): self
    {
        return self::create([
            'name' => 'Double XP Boost',
            'type' => 'double_xp',
            'description' => 'Tous les gains XP sont doublés !',
            'multiplier' => 2.00,
            'start_at' => now(),
            'end_at' => now()->addHours($durationHours),
            'is_active' => true,
        ]);
    }

    public static function createWeekendBonus(): self
    {
        $nextSaturday = now()->next(Carbon::SATURDAY);

        return self::create([
            'name' => 'Weekend Warrior',
            'type' => 'weekend_bonus',
            'description' => '+50% XP tout le weekend !',
            'multiplier' => 1.50,
            'start_at' => $nextSaturday,
            'end_at' => $nextSaturday->copy()->addDays(2),
            'is_active' => true,
        ]);
    }
}
