<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Projection extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'financial_goal_id',
        'type',
        'projected_date',
        'monthly_saving_required',
        'projected_amount',
        'confidence_score',
        'assumptions',
        'milestones',
        'recommendation',
        'status',
        'calculated_at',
        'expires_at',
        'calculation_data'
    ];

    protected $casts = [
        'projected_date' => 'date',
        'calculated_at' => 'date',
        'expires_at' => 'date',
        'monthly_saving_required' => 'decimal:2',
        'projected_amount' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'assumptions' => 'array',
        'milestones' => 'array',
        'calculation_data' => 'array'
    ];

    protected $dates = [
        'projected_date',
        'calculated_at',
        'expires_at',
        'deleted_at'
    ];

    protected $attributes = [
        'type' => 'realistic',
        'confidence_score' => 0.50,
        'status' => 'active',
        'calculated_at' => null // Sera défini automatiquement
    ];

    /**
     * Types de projections
     */
    public const TYPE_OPTIMISTIC = 'optimistic';
    public const TYPE_REALISTIC = 'realistic';
    public const TYPE_PESSIMISTIC = 'pessimistic';

    public const TYPES = [
        self::TYPE_OPTIMISTIC => 'Optimiste',
        self::TYPE_REALISTIC => 'Réaliste',
        self::TYPE_PESSIMISTIC => 'Pessimiste'
    ];

    /**
     * Statuts de projections
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OUTDATED = 'outdated';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_OUTDATED => 'Obsolète',
        self::STATUS_ARCHIVED => 'Archivée'
    ];

    /**
     * Relation avec l'objectif financier
     */
    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }

    /**
     * Alias pour compatibilité
     */
    public function goal(): BelongsTo
    {
        return $this->financialGoal();
    }

    /**
     * Scope pour filtrer par objectif
     */
    public function scopeForGoal($query, $goalId)
    {
        return $query->where('financial_goal_id', $goalId);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les projections actives
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope pour les projections valides (non expirées)
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope pour ordonner par confiance
     */
    public function scopeOrderedByConfidence($query)
    {
        return $query->orderBy('confidence_score', 'desc');
    }

    /**
     * Scope pour les projections récentes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('calculated_at', '>=', now()->subDays($days));
    }

    /**
     * Accessor pour le nom du type
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor pour le nom du statut
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor pour vérifier si la projection est expirée
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Accessor pour vérifier si la projection est valide
     */
    public function getIsValidAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->is_expired;
    }

    /**
     * Accessor pour le score de confiance en pourcentage
     */
    public function getConfidencePercentageAttribute(): int
    {
        return (int) ($this->confidence_score * 100);
    }

    /**
     * Accessor pour les jours jusqu'à la date projetée
     */
    public function getDaysToProjectedDateAttribute(): int
    {
        return max(0, now()->diffInDays($this->projected_date, false));
    }

    /**
     * Accessor pour vérifier si la projection est réalisable
     */
    public function getIsFeasibleAttribute(): bool
    {
        return $this->confidence_score >= 0.3; // Seuil de faisabilité
    }

    /**
     * Marquer comme obsolète
     */
    public function markAsOutdated(): bool
    {
        return $this->update(['status' => self::STATUS_OUTDATED]);
    }

    /**
     * Archiver la projection
     */
    public function archive(): bool
    {
        return $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Calculer une nouvelle projection basée sur les données actuelles
     */
    public static function calculateForGoal(FinancialGoal $goal, string $type = self::TYPE_REALISTIC): self
    {
        $calculator = new ProjectionCalculator($goal);
        $projectionData = $calculator->calculate($type);

        return self::create([
            'financial_goal_id' => $goal->id,
            'type' => $type,
            'projected_date' => $projectionData['projected_date'],
            'monthly_saving_required' => $projectionData['monthly_saving_required'],
            'projected_amount' => $projectionData['projected_amount'],
            'confidence_score' => $projectionData['confidence_score'],
            'assumptions' => $projectionData['assumptions'],
            'milestones' => $projectionData['milestones'],
            'recommendation' => $projectionData['recommendation'],
            'calculation_data' => $projectionData['calculation_data'],
            'calculated_at' => now(),
            'expires_at' => now()->addDays(7) // Expire après 7 jours
        ]);
    }

    /**
     * Obtenir les étapes intermédiaires jusqu'à la date projetée
     */
    public function getMilestoneProgress(): array
    {
        if (!$this->milestones) {
            return [];
        }

        $progress = [];
        $goal = $this->financialGoal;

        foreach ($this->milestones as $milestone) {
            $targetAmount = ($milestone['percentage'] / 100) * $goal->target_amount;
            $isReached = $goal->current_amount >= $targetAmount;

            $progress[] = [
                'percentage' => $milestone['percentage'],
                'target_amount' => $targetAmount,
                'projected_date' => $milestone['date'],
                'is_reached' => $isReached,
                'description' => $milestone['description'] ?? null
            ];
        }

        return $progress;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($projection) {
            if (!$projection->calculated_at) {
                $projection->calculated_at = now();
            }
        });
    }
}
