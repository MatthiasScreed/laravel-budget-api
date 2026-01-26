<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialGoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'target_amount',
        'current_amount',
        'target_date',
        'start_date',
        'status',
        'type',
        'priority',
        'color',
        'icon',
        'monthly_target', // ✅ Déjà présent
        'is_automatic',
        'automatic_amount',
        'automatic_frequency',
        'next_automatic_date',
        'milestones',
        'notes',
        'is_shared',
        'tags',
        'completed_at',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'monthly_target' => 'decimal:2', // ✅ Déjà présent
        'automatic_amount' => 'decimal:2',
        'target_date' => 'date',
        'start_date' => 'date',
        'next_automatic_date' => 'date',
        'completed_at' => 'date',
        'priority' => 'integer',
        'is_automatic' => 'boolean',
        'is_shared' => 'boolean',
        'milestones' => 'array',
        'tags' => 'array',
    ];

    protected $dates = [
        'target_date',
        'start_date',
        'next_automatic_date',
        'completed_at',
        'deleted_at',
    ];

    protected $attributes = [
        'current_amount' => 0,
        'start_date' => null,
        'status' => 'active',
        'type' => 'savings',
        'priority' => 3,
        'color' => '#3B82F6',
        'is_automatic' => false,
        'is_shared' => false,
    ];

    // ✅ Ajouter à $appends pour exposer les calculs
    protected $appends = [
        'progress_percentage',
        'remaining_amount',
        'is_reached',
        'days_remaining',
        'is_overdue',
        'suggested_monthly_amount',
        'months_remaining',           // ✅ NOUVEAU
        'projected_completion_date',  // ✅ NOUVEAU
        'can_accelerate',             // ✅ NOUVEAU
    ];

    /**
     * Les statuts d'objectifs
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_COMPLETED => 'Terminé',
        self::STATUS_PAUSED => 'En pause',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    /**
     * Les types d'objectifs
     */
    public const TYPE_SAVINGS = 'savings';

    public const TYPE_DEBT_PAYOFF = 'debt_payoff';

    public const TYPE_INVESTMENT = 'investment';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_EMERGENCY_FUND = 'emergency_fund';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_SAVINGS => 'Épargne',
        self::TYPE_DEBT_PAYOFF => 'Remboursement de dette',
        self::TYPE_INVESTMENT => 'Investissement',
        self::TYPE_PURCHASE => 'Achat',
        self::TYPE_EMERGENCY_FUND => 'Fonds d\'urgence',
        self::TYPE_OTHER => 'Autre',
    ];

    /**
     * Les fréquences automatiques
     */
    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCIES = [
        self::FREQUENCY_WEEKLY => 'Hebdomadaire',
        self::FREQUENCY_MONTHLY => 'Mensuelle',
        self::FREQUENCY_QUARTERLY => 'Trimestrielle',
    ];

    /**
     * Les niveaux de priorité
     */
    public const PRIORITIES = [
        1 => 'Très haute',
        2 => 'Haute',
        3 => 'Moyenne',
        4 => 'Basse',
        5 => 'Très basse',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class, 'financial_goal_id');
    }

    public function projections(): HasMany
    {
        return $this->hasMany(Projection::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->withStatus(self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->withStatus(self::STATUS_COMPLETED);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority')->orderBy('target_date');
    }

    public function scopeDueSoon($query, $days = 30)
    {
        return $query->where('target_date', '<=', now()->addDays($days))
            ->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAutomaticDue($query)
    {
        return $query->where('is_automatic', true)
            ->where('next_automatic_date', '<=', now())
            ->active();
    }

    /**
     * ✅ Scope pour objectifs avec contribution mensuelle définie
     */
    public function scopeWithMonthlyTarget($query)
    {
        return $query->where('monthly_target', '>', 0);
    }

    // ==========================================
    // ACCESSORS - Existants
    // ==========================================

    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getPriorityNameAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Inconnue';
    }

    public function getAutomaticFrequencyNameAttribute(): ?string
    {
        return $this->automatic_frequency ?
            (self::FREQUENCIES[$this->automatic_frequency] ?? $this->automatic_frequency) :
            null;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->current_amount / $this->target_amount) * 100, 2));
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->target_amount - $this->current_amount);
    }

    public function getIsReachedAttribute(): bool
    {
        return $this->current_amount >= $this->target_amount;
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->target_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->target_date, false));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date &&
            $this->target_date->isPast() &&
            ! $this->is_reached &&
            $this->status === self::STATUS_ACTIVE;
    }

    public function getSuggestedMonthlyAmountAttribute(): ?float
    {
        if (! $this->target_date || $this->is_reached) {
            return null;
        }

        $monthsRemaining = max(1, now()->diffInMonths($this->target_date));

        return round($this->remaining_amount / $monthsRemaining, 2);
    }

    public function getFormattedTargetAmountAttribute(): string
    {
        return number_format($this->target_amount, 2, ',', ' ').' €';
    }

    public function getFormattedCurrentAmountAttribute(): string
    {
        return number_format($this->current_amount, 2, ',', ' ').' €';
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return number_format($this->remaining_amount, 2, ',', ' ').' €';
    }

    // ==========================================
    // ✅ NOUVEAUX ACCESSORS - Calculs CoinQuest
    // ==========================================

    /**
     * ✅ Nombre de mois restants basé sur monthly_target
     */
    public function getMonthsRemainingAttribute(): ?float
    {
        if (! $this->monthly_target || $this->monthly_target <= 0) {
            return null; // Pas de contribution mensuelle définie
        }

        $remaining = $this->remaining_amount;

        if ($remaining <= 0) {
            return 0; // Objectif déjà atteint
        }

        return round($remaining / $this->monthly_target, 1);
    }

    /**
     * ✅ Date de complétion projetée
     */
    public function getProjectedCompletionDateAttribute(): ?string
    {
        if (! $this->months_remaining) {
            return null;
        }

        return Carbon::now()
            ->addMonths(ceil($this->months_remaining))
            ->format('Y-m-d');
    }

    /**
     * ✅ Peut être accéléré avec la capacité d'épargne
     */
    public function getCanAccelerateAttribute(): bool
    {
        return $this->remaining_amount > 0 &&
            $this->monthly_target > 0 &&
            $this->status === self::STATUS_ACTIVE;
    }

    // ==========================================
    // ✅ MÉTHODES - Simulations
    // ==========================================

    /**
     * ✅ Calculer le montant pour accélérer de X mois
     */
    public function calculateAccelerationAmount(int $monthsToReduce): float
    {
        if (! $this->monthly_target || $this->monthly_target <= 0) {
            return 0;
        }

        return min(
            $monthsToReduce * $this->monthly_target,
            $this->remaining_amount
        );
    }

    /**
     * ✅ Simuler l'impact d'un versement ponctuel
     */
    public function simulateContribution(float $amount): array
    {
        $newCurrent = $this->current_amount + $amount;
        $newRemaining = max(0, $this->target_amount - $newCurrent);

        $newMonthsRemaining = null;
        $monthsSaved = null;

        if ($this->monthly_target > 0 && $newRemaining > 0) {
            $newMonthsRemaining = round($newRemaining / $this->monthly_target, 1);

            if ($this->months_remaining) {
                $monthsSaved = round($this->months_remaining - $newMonthsRemaining, 1);
            }
        }

        $newCompletionDate = null;
        if ($newMonthsRemaining && $newMonthsRemaining > 0) {
            $newCompletionDate = Carbon::now()
                ->addMonths(ceil($newMonthsRemaining))
                ->format('Y-m-d');
        }

        return [
            'new_current_amount' => round($newCurrent, 2),
            'new_remaining' => round($newRemaining, 2),
            'new_progress_percentage' => $this->target_amount > 0 ?
                round(($newCurrent / $this->target_amount) * 100, 2) : 0,
            'new_months_remaining' => $newMonthsRemaining,
            'months_saved' => $monthsSaved,
            'new_completion_date' => $newCompletionDate,
            'is_completed' => $newRemaining <= 0,
        ];
    }

    // ==========================================
    // MUTATORS
    // ==========================================

    public function setColorAttribute($value)
    {
        if ($value && ! preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            $value = '#3B82F6';
        }
        $this->attributes['color'] = $value;
    }

    // ==========================================
    // MÉTHODES - Existantes
    // ==========================================

    public function recalculateCurrentAmount(): bool
    {
        $totalContributions = $this->contributions()->sum('amount');

        return $this->update(['current_amount' => $totalContributions]);
    }

    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function pause(): bool
    {
        return $this->update(['status' => self::STATUS_PAUSED]);
    }

    public function resume(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function cancel(): bool
    {
        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function scheduleNextAutomaticContribution(): void
    {
        if (! $this->is_automatic || ! $this->automatic_frequency) {
            return;
        }

        $nextDate = match ($this->automatic_frequency) {
            self::FREQUENCY_WEEKLY => now()->addWeek(),
            self::FREQUENCY_MONTHLY => now()->addMonth(),
            self::FREQUENCY_QUARTERLY => now()->addMonths(3),
            default => null
        };

        if ($nextDate) {
            $this->update(['next_automatic_date' => $nextDate]);
        }
    }

    public function getReachedMilestones(): array
    {
        $milestones = $this->milestones ?? [25, 50, 75, 100];
        $reached = [];

        foreach ($milestones as $milestone) {
            if ($this->progress_percentage >= $milestone) {
                $reached[] = $milestone;
            }
        }

        return $reached;
    }

    public function belongsToUser($userId): bool
    {
        return $this->user_id == $userId;
    }

    // ==========================================
    // BOOT
    // ==========================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($goal) {
            if (! $goal->start_date) {
                $goal->start_date = now();
            }
        });

        static::updating(function ($goal) {
            if ($goal->isDirty('current_amount') &&
                $goal->current_amount >= $goal->target_amount &&
                $goal->status === self::STATUS_ACTIVE) {

                $goal->status = self::STATUS_COMPLETED;
                $goal->completed_at = now();
            }
        });
    }
}
