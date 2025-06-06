<?php

namespace App\Models;

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
        'monthly_target',
        'is_automatic',
        'automatic_amount',
        'automatic_frequency',
        'next_automatic_date',
        'milestones',
        'notes',
        'is_shared',
        'tags',
        'completed_at'
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'monthly_target' => 'decimal:2',
        'automatic_amount' => 'decimal:2',
        'target_date' => 'date',
        'start_date' => 'date',
        'next_automatic_date' => 'date',
        'completed_at' => 'date',
        'priority' => 'integer',
        'is_automatic' => 'boolean',
        'is_shared' => 'boolean',
        'milestones' => 'array',
        'tags' => 'array'
    ];

    protected $dates = [
        'target_date',
        'start_date',
        'next_automatic_date',
        'completed_at',
        'deleted_at'
    ];

    protected $attributes = [
        'current_amount' => 0,
        'start_date' => null, // Sera défini automatiquement
        'status' => 'active',
        'type' => 'savings',
        'priority' => 3,
        'color' => '#3B82F6',
        'is_automatic' => false,
        'is_shared' => false
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
        self::STATUS_CANCELLED => 'Annulé'
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
        self::TYPE_OTHER => 'Autre'
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
        self::FREQUENCY_QUARTERLY => 'Trimestrielle'
    ];

    /**
     * Les niveaux de priorité
     */
    public const PRIORITIES = [
        1 => 'Très haute',
        2 => 'Haute',
        3 => 'Moyenne',
        4 => 'Basse',
        5 => 'Très basse'
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les objectifs actifs
     */
    public function scopeActive($query)
    {
        return $query->withStatus(self::STATUS_ACTIVE);
    }

    /**
     * Scope pour les objectifs terminés
     */
    public function scopeCompleted($query)
    {
        return $query->withStatus(self::STATUS_COMPLETED);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour filtrer par priorité
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope pour les objectifs avec contributions automatiques
     */
    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    /**
     * Scope pour les objectifs partagés
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope pour ordonner par priorité
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority')->orderBy('target_date');
    }

    /**
     * Scope pour les objectifs proches de l'échéance
     */
    public function scopeDueSoon($query, $days = 30)
    {
        return $query->where('target_date', '<=', now()->addDays($days))
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope pour les contributions automatiques dues
     */
    public function scopeAutomaticDue($query)
    {
        return $query->where('is_automatic', true)
            ->where('next_automatic_date', '<=', now())
            ->active();
    }

    /**
     * Accessor pour le nom du statut
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor pour le nom du type
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor pour le nom de la priorité
     */
    public function getPriorityNameAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Inconnue';
    }

    /**
     * Accessor pour le nom de la fréquence
     */
    public function getAutomaticFrequencyNameAttribute(): ?string
    {
        return $this->automatic_frequency ?
            (self::FREQUENCIES[$this->automatic_frequency] ?? $this->automatic_frequency) :
            null;
    }

    /**
     * Accessor pour le pourcentage de progression
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }
        return min(100, ($this->current_amount / $this->target_amount) * 100);
    }

    /**
     * Accessor pour le montant restant
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->target_amount - $this->current_amount);
    }

    /**
     * Accessor pour vérifier si l'objectif est atteint
     */
    public function getIsReachedAttribute(): bool
    {
        return $this->current_amount >= $this->target_amount;
    }

    /**
     * Accessor pour les jours restants
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->target_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->target_date, false));
    }

    /**
     * Accessor pour vérifier si l'objectif est en retard
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date &&
            $this->target_date->isPast() &&
            !$this->is_reached &&
            $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Accessor pour le montant mensuel suggéré
     */
    public function getSuggestedMonthlyAmountAttribute(): ?float
    {
        if (!$this->target_date || $this->is_reached) {
            return null;
        }

        $monthsRemaining = max(1, now()->diffInMonths($this->target_date));
        return $this->remaining_amount / $monthsRemaining;
    }

    /**
     * Accessor pour les montants formatés
     */
    public function getFormattedTargetAmountAttribute(): string
    {
        return number_format($this->target_amount, 2, ',', ' ') . ' €';
    }

    public function getFormattedCurrentAmountAttribute(): string
    {
        return number_format($this->current_amount, 2, ',', ' ') . ' €';
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return number_format($this->remaining_amount, 2, ',', ' ') . ' €';
    }

    /**
     * Mutator pour la couleur
     */
    public function setColorAttribute($value)
    {
        if ($value && !preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            $value = '#3B82F6'; // Couleur par défaut
        }
        $this->attributes['color'] = $value;
    }

    /**
     * Recalculer le montant actuel depuis les contributions
     */
    public function recalculateCurrentAmount(): bool
    {
        $totalContributions = $this->contributions()->sum('amount');
        return $this->update(['current_amount' => $totalContributions]);
    }

    /**
     * Marquer comme terminé
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now()
        ]);
    }

    /**
     * Mettre en pause
     */
    public function pause(): bool
    {
        return $this->update(['status' => self::STATUS_PAUSED]);
    }

    /**
     * Reprendre un objectif en pause
     */
    public function resume(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Annuler l'objectif
     */
    public function cancel(): bool
    {
        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Programmer la prochaine contribution automatique
     */
    public function scheduleNextAutomaticContribution(): void
    {
        if (!$this->is_automatic || !$this->automatic_frequency) {
            return;
        }

        $nextDate = match($this->automatic_frequency) {
            self::FREQUENCY_WEEKLY => now()->addWeek(),
            self::FREQUENCY_MONTHLY => now()->addMonth(),
            self::FREQUENCY_QUARTERLY => now()->addMonths(3),
            default => null
        };

        if ($nextDate) {
            $this->update(['next_automatic_date' => $nextDate]);
        }
    }

    /**
     * Obtenir les étapes franchies
     */
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

    /**
     * Vérifier si l'objectif appartient à un utilisateur
     */
    public function belongsToUser($userId): bool
    {
        return $this->user_id == $userId;
    }

    /**
     * Boot method pour gérer les événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Définir la date de début automatiquement
        static::creating(function ($goal) {
            if (!$goal->start_date) {
                $goal->start_date = now();
            }
        });

        // Vérifier l'achèvement automatiquement
        static::updating(function ($goal) {
            if ($goal->isDirty('current_amount') &&
                $goal->current_amount >= $goal->target_amount &&
                $goal->status === self::STATUS_ACTIVE) {

                $goal->status = self::STATUS_COMPLETED;
                $goal->completed_at = now();
            }
        });
    }



    public function projections()
    {
        return $this->hasMany(Projection::class);
    }

    public function suggestions()
    {
        return $this->hasMany(Suggestion::class);
    }
}
