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
        'bonus_claimed_at', // ✅ AJOUTÉ
    ];

    protected $casts = [
        'current_count' => 'integer',
        'best_count' => 'integer',
        'last_activity_date' => 'date',
        'bonus_claimed_at' => 'datetime', // ✅ AJOUTÉ
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'current_count' => 0,
        'best_count' => 0,
        'is_active' => true,
    ];

    /**
     * Types de séries
     */
    public const TYPE_DAILY_LOGIN = 'daily_login';

    public const TYPE_DAILY_TRANSACTION = 'daily_transaction';

    public const TYPE_WEEKLY_BUDGET = 'weekly_budget';

    public const TYPE_MONTHLY_SAVING = 'monthly_saving';

    public const TYPES = [
        self::TYPE_DAILY_LOGIN => 'Connexion quotidienne',
        self::TYPE_DAILY_TRANSACTION => 'Transaction quotidienne',
        self::TYPE_WEEKLY_BUDGET => 'Budget hebdomadaire',
        self::TYPE_MONTHLY_SAVING => 'Épargne mensuelle',
    ];

    /**
     * Relation avec l'utilisateur
     */
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

        // Déjà fait aujourd'hui ?
        if ($this->last_activity_date && $this->last_activity_date->toDateString() === $today) {
            return false; // Déjà comptabilisé aujourd'hui
        }

        $yesterday = now()->subDay()->toDateString();

        // Vérifier la continuité
        if (! $this->last_activity_date) {
            // Première fois
            $this->current_count = 1;
        } elseif ($this->last_activity_date->toDateString() === $yesterday) {
            // Streak continue
            $this->current_count++;
        } else {
            // Streak brisée, recommencer
            $this->current_count = 1;
        }

        // Mettre à jour le record
        if ($this->current_count > $this->best_count) {
            $this->best_count = $this->current_count;
        }

        $this->last_activity_date = now();
        $this->is_active = true;
        $this->save();

        return true;
    }

    /**
     * Vérifier si streak peut être réclamée
     */
    public function canClaimBonus(): bool
    {
        if ($this->current_count < 3) {
            return false;
        } // Minimum 3 jours

        // Bonus disponible chaque semaine (7 jours)
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

        $baseBonus = 20;
        $streakMultiplier = floor($this->current_count / 7);
        $typeBonus = match ($this->type) {
            self::TYPE_DAILY_LOGIN => 5,
            self::TYPE_DAILY_TRANSACTION => 15,
            self::TYPE_WEEKLY_BUDGET => 25,
            self::TYPE_MONTHLY_SAVING => 50,
            default => 10
        };

        return $baseBonus + ($streakMultiplier * 10) + $typeBonus;
    }

    /**
     * Réclamer le bonus
     */
    public function claimBonus(): int
    {
        if (! $this->canClaimBonus()) {
            return 0;
        }

        $bonusXp = $this->calculateBonusXp();
        $this->bonus_claimed_at = now();
        $this->save();

        return $bonusXp;
    }

    /**
     * Obtenir les milestones
     */
    public function getMilestones(): array
    {
        return [3, 7, 14, 21, 30, 60, 100, 365];
    }

    /**
     * Prochain milestone
     */
    public function getNextMilestone(): ?int
    {
        foreach ($this->getMilestones() as $milestone) {
            if ($this->current_count < $milestone) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * Vérifier si streak est à un milestone important
     */
    public function isAtMilestone(): bool
    {
        return in_array($this->current_count, $this->getMilestones());
    }

    /**
     * Risque de perdre la streak
     */
    public function getRiskLevel(): string
    {
        if (! $this->last_activity_date) {
            return 'none';
        }

        $hoursSinceLastActivity = $this->last_activity_date->diffInHours(now());

        return match (true) {
            $hoursSinceLastActivity > 20 => 'critical', // Plus de 20h
            $hoursSinceLastActivity > 12 => 'high',     // Plus de 12h
            $hoursSinceLastActivity > 6 => 'medium',    // Plus de 6h
            default => 'low'
        };
    }

    /**
     * Vérifier si la série est brisée
     */
    public function checkIfBroken(): bool
    {
        if (! $this->last_activity_date) {
            return false;
        }

        $daysSinceLastActivity = now()->diffInDays($this->last_activity_date);

        if ($daysSinceLastActivity > 1) {
            $this->current_count = 0;
            $this->is_active = false;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Réactiver la série
     */
    public function reactivate(): void
    {
        $this->is_active = true;
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
        // Logique pour filtrer par niveau de risque
        return $query->where('is_active', true);
    }
}
