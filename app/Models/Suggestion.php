<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suggestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'priority',
        'title',
        'message',
        'action_data',
        'potential_impact',
        'impact_type',
        'financial_goal_id',
        'category_id',
        'transaction_id',
        'status',
        'seen_at',
        'acted_at',
        'dismissed_at',
        'expires_at',
        'feedback',
        'confidence_score',
        'source',
        'calculation_basis',
    ];

    protected $casts = [
        'action_data' => 'array',
        'potential_impact' => 'decimal:2',
        'feedback' => 'array',
        'confidence_score' => 'decimal:2',
        'calculation_basis' => 'array',
        'seen_at' => 'datetime',
        'acted_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'expires_at' => 'date',
    ];

    protected $dates = [
        'seen_at',
        'acted_at',
        'dismissed_at',
        'expires_at',
        'deleted_at',
    ];

    protected $attributes = [
        'priority' => 'medium',
        'status' => 'pending',
        'confidence_score' => 0.50,
        'source' => 'system',
    ];

    /**
     * Types de suggestions
     */
    public const TYPE_REDUCE_EXPENSE = 'reduce_expense';

    public const TYPE_INCREASE_INCOME = 'increase_income';

    public const TYPE_OPTIMIZE_SAVINGS = 'optimize_savings';

    public const TYPE_CATEGORY_REBALANCE = 'category_rebalance';

    public const TYPE_GOAL_ADJUSTMENT = 'goal_adjustment';

    public const TYPE_BUDGET_ALERT = 'budget_alert';

    public const TYPE_INVESTMENT_OPPORTUNITY = 'investment_opportunity';

    public const TYPE_DEBT_OPTIMIZATION = 'debt_optimization';

    public const TYPES = [
        self::TYPE_REDUCE_EXPENSE => 'Réduire les dépenses',
        self::TYPE_INCREASE_INCOME => 'Augmenter les revenus',
        self::TYPE_OPTIMIZE_SAVINGS => 'Optimiser l\'épargne',
        self::TYPE_CATEGORY_REBALANCE => 'Rééquilibrer les catégories',
        self::TYPE_GOAL_ADJUSTMENT => 'Ajuster les objectifs',
        self::TYPE_BUDGET_ALERT => 'Alerte budget',
        self::TYPE_INVESTMENT_OPPORTUNITY => 'Opportunité d\'investissement',
        self::TYPE_DEBT_OPTIMIZATION => 'Optimisation des dettes',
    ];

    /**
     * Priorités
     */
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Basse',
        self::PRIORITY_MEDIUM => 'Moyenne',
        self::PRIORITY_HIGH => 'Haute',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    /**
     * Types d'impact
     */
    public const IMPACT_SAVINGS = 'savings';

    public const IMPACT_INCOME = 'income';

    public const IMPACT_GOAL_ACHIEVEMENT = 'goal_achievement';

    public const IMPACT_TYPES = [
        self::IMPACT_SAVINGS => 'Économies',
        self::IMPACT_INCOME => 'Revenus',
        self::IMPACT_GOAL_ACHIEVEMENT => 'Atteinte d\'objectif',
    ];

    /**
     * Statuts
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SEEN = 'seen';

    public const STATUS_ACTED = 'acted';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_SEEN => 'Vue',
        self::STATUS_ACTED => 'Action prise',
        self::STATUS_DISMISSED => 'Rejetée',
        self::STATUS_EXPIRED => 'Expirée',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec l'objectif financier
     */
    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }

    /**
     * Relation avec la catégorie
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relation avec la transaction
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
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
     * Scope pour filtrer par statut
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les suggestions en attente
     */
    public function scopePending($query)
    {
        return $query->withStatus(self::STATUS_PENDING);
    }

    /**
     * Scope pour les suggestions vues
     */
    public function scopeSeen($query)
    {
        return $query->withStatus(self::STATUS_SEEN);
    }

    /**
     * Scope pour les suggestions actionnées
     */
    public function scopeActed($query)
    {
        return $query->withStatus(self::STATUS_ACTED);
    }

    /**
     * Scope pour les suggestions non expirées
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope pour les suggestions urgentes
     */
    public function scopeUrgent($query)
    {
        return $query->withPriority(self::PRIORITY_URGENT);
    }

    /**
     * Scope pour ordonner par priorité et date
     */
    public function scopeOrderedByPriority($query)
    {
        $priorityOrder = [
            self::PRIORITY_URGENT => 1,
            self::PRIORITY_HIGH => 2,
            self::PRIORITY_MEDIUM => 3,
            self::PRIORITY_LOW => 4,
        ];

        return $query->orderByRaw(
            "CASE priority
             WHEN 'urgent' THEN 1
             WHEN 'high' THEN 2
             WHEN 'medium' THEN 3
             WHEN 'low' THEN 4
             END"
        )->orderBy('created_at', 'desc');
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
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Accessor pour le nom du statut
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor pour le nom du type d'impact
     */
    public function getImpactTypeNameAttribute(): ?string
    {
        return $this->impact_type ?
            (self::IMPACT_TYPES[$this->impact_type] ?? $this->impact_type) :
            null;
    }

    /**
     * Accessor pour vérifier si la suggestion est expirée
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Accessor pour vérifier si la suggestion est actionnable
     */
    public function getIsActionableAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SEEN]) &&
            ! $this->is_expired;
    }

    /**
     * Accessor pour le score de confiance en pourcentage
     */
    public function getConfidencePercentageAttribute(): int
    {
        return (int) ($this->confidence_score * 100);
    }

    /**
     * Accessor pour l'impact formaté
     */
    public function getFormattedPotentialImpactAttribute(): ?string
    {
        if (! $this->potential_impact) {
            return null;
        }

        $sign = in_array($this->impact_type, [self::IMPACT_SAVINGS, self::IMPACT_INCOME]) ? '+' : '';

        return $sign.number_format($this->potential_impact, 2, ',', ' ').' €';
    }

    /**
     * Marquer comme vue
     */
    public function markAsSeen(): bool
    {
        if ($this->status === self::STATUS_PENDING) {
            return $this->update([
                'status' => self::STATUS_SEEN,
                'seen_at' => now(),
            ]);
        }

        return false;
    }

    /**
     * Marquer comme actionnée
     */
    public function markAsActed(?array $feedback = null): bool
    {
        $updateData = [
            'status' => self::STATUS_ACTED,
            'acted_at' => now(),
        ];

        if ($feedback) {
            $updateData['feedback'] = $feedback;
        }

        return $this->update($updateData);
    }

    /**
     * Rejeter la suggestion
     */
    public function dismiss(?array $reason = null): bool
    {
        $updateData = [
            'status' => self::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ];

        if ($reason) {
            $updateData['feedback'] = $reason;
        }

        return $this->update($updateData);
    }

    /**
     * Vérifier si la suggestion appartient à un utilisateur
     */
    public function belongsToUser($userId): bool
    {
        return $this->user_id == $userId;
    }

    /**
     * Créer une suggestion intelligente
     */
    public static function createIntelligent(array $data): self
    {
        $generator = new SuggestionGenerator($data['user_id']);
        $suggestionData = $generator->generate($data);

        return self::create(array_merge($data, $suggestionData));
    }

    /**
     * Obtenir les suggestions par priorité pour un utilisateur
     */
    public static function getForUserByPriority($userId, $limit = 10)
    {
        return self::forUser($userId)
            ->valid()
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SEEN])
            ->orderedByPriority()
            ->limit($limit)
            ->get();
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Marquer comme expirée si la date d'expiration est dépassée
        static::updating(function ($suggestion) {
            if ($suggestion->is_expired && $suggestion->status !== self::STATUS_EXPIRED) {
                $suggestion->status = self::STATUS_EXPIRED;
            }
        });
    }
}
