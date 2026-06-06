<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Streak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'current_count',
        'best_count',
        'last_activity_date',
        'is_active',
        'bonus_claimed_at',
    ];

    protected $casts = [
        'current_count'      => 'integer',
        'best_count'         => 'integer',
        'last_activity_date' => 'date',
        'bonus_claimed_at'   => 'datetime',
        'is_active'          => 'boolean',
    ];

    protected $attributes = [
        'current_count' => 0,
        'best_count'    => 0,
        'is_active'     => true,
    ];

    public const TYPE_DAILY_LOGIN       = 'daily_login';
    public const TYPE_DAILY_TRANSACTION = 'daily_transaction';
    public const TYPE_WEEKLY_BUDGET     = 'weekly_budget';
    public const TYPE_MONTHLY_SAVING    = 'monthly_saving';

    public const TYPES = [
        self::TYPE_DAILY_LOGIN       => 'Connexion quotidienne',
        self::TYPE_DAILY_TRANSACTION => 'Transaction quotidienne',
        self::TYPE_WEEKLY_BUDGET     => 'Budget hebdomadaire',
        self::TYPE_MONTHLY_SAVING    => 'Épargne mensuelle',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Incrémenter la série
     */
    public function incrementStreak(): bool
    {
        $today = now()->toDateString();

        if ($this->last_activity_date && $this->last_activity_date->toDateString() === $today) {
            return false;
        }

        $yesterday = now()->subDay()->toDateString();

        if (! $this->last_activity_date) {
            $this->current_count = 1;
        } elseif ($this->last_activity_date->toDateString() === $yesterday) {
            $this->current_count++;
        } else {
            $this->current_count = 1;
        }

        if ($this->current_count > $this->best_count) {
            $this->best_count = $this->current_count;
        }

        $this->last_activity_date = now();
        $this->is_active          = true;
        $this->save();

        return true;
    }

    /**
     * 🧊 La streak est-elle éligible à une protection par freeze ?
     * Conditions : au moins 2 jours de série, et exactement 1 jour manqué
     */
    public function isEligibleForFreeze(): bool
    {
        if (! $this->last_activity_date || $this->current_count < 2) {
            return false;
        }

        $daysMissed = now()->startOfDay()->diffInDays(
            $this->last_activity_date->copy()->startOfDay()
        );

        return $daysMissed === 1;
    }

    public function canClaimBonus(): bool
    {
        if ($this->current_count < 3) {
            return false;
        }

        if ($this->current_count % 7 === 0) {
            $lastClaim = $this->bonus_claimed_at;
            if (! $lastClaim || $lastClaim->diffInDays(now()) >= 7) {
                return true;
            }
        }

        return false;
    }

    public function calculateBonusXp(): int
    {
        if (! $this->canClaimBonus()) {
            return 0;
        }

        $baseBonus        = 20;
        $streakMultiplier = floor($this->current_count / 7);
        $typeBonus        = match ($this->type) {
            self::TYPE_DAILY_LOGIN       => 5,
            self::TYPE_DAILY_TRANSACTION => 15,
            self::TYPE_WEEKLY_BUDGET     => 25,
            self::TYPE_MONTHLY_SAVING    => 50,
            default                      => 10
        };

        return $baseBonus + ($streakMultiplier * 10) + $typeBonus;
    }

    public function claimBonus(): int
    {
        if (! $this->canClaimBonus()) {
            return 0;
        }

        $bonusXp              = $this->calculateBonusXp();
        $this->bonus_claimed_at = now();
        $this->save();

        return $bonusXp;
    }

    public function getMilestones(): array
    {
        return [3, 7, 14, 21, 30, 60, 100, 365];
    }

    public function getNextMilestone(): ?int
    {
        foreach ($this->getMilestones() as $milestone) {
            if ($this->current_count < $milestone) {
                return $milestone;
            }
        }

        return null;
    }

    public function isAtMilestone(): bool
    {
        return in_array($this->current_count, $this->getMilestones());
    }

    public function getRiskLevel(): string
    {
        if (! $this->last_activity_date) {
            return 'none';
        }

        $hoursSinceLastActivity = $this->last_activity_date->diffInHours(now());

        return match (true) {
            $hoursSinceLastActivity > 20 => 'critical',
            $hoursSinceLastActivity > 12 => 'high',
            $hoursSinceLastActivity > 6  => 'medium',
            default                      => 'low'
        };
    }

    public function checkIfBroken(): bool
    {
        if (! $this->last_activity_date) {
            return false;
        }

        $daysSinceLastActivity = now()->diffInDays($this->last_activity_date);

        if ($daysSinceLastActivity > 1) {
            $this->current_count = 0;
            $this->is_active     = false;
            $this->save();

            return true;
        }

        return false;
    }

    public function reactivate(): void
    {
        $this->is_active     = true;
        $this->current_count = 0;
        $this->save();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithRisk($query, $level)
    {
        return $query->where('is_active', true);
    }
}
