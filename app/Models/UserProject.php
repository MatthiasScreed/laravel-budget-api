<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'financial_goal_id',
        'template_key',
        'name',
        'description',
        'status',
        'start_date',
        'target_date',
        'completed_date',
        'custom_categories',
        'milestones',
        'settings',
        'budget_allocated',
        'budget_spent',
        'priority',
        'collaborators',
        'is_shared',
        'notifications_settings',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
            'completed_date' => 'date',
            'custom_categories' => 'array',
            'milestones' => 'array',
            'settings' => 'array',
            'collaborators' => 'array',
            'notifications_settings' => 'array',
            'budget_allocated' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'is_shared' => 'boolean',
        ];
    }

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
     * Relation avec le template (optionnel)
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'template_key', 'key');
    }

    /**
     * Scope pour les projets actifs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope pour les projets d'un utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour les projets en cours
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['planning', 'active']);
    }

    /**
     * Scope pour les projets complétés
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Calculer le progrès en pourcentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->budget_allocated == 0) {
            return 0;
        }

        return min(100, ($this->budget_spent / $this->budget_allocated) * 100);
    }

    /**
     * Calculer le budget restant
     */
    public function getRemainingBudgetAttribute(): float
    {
        return max(0, $this->budget_allocated - $this->budget_spent);
    }

    /**
     * Vérifier si le projet est en retard
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date &&
            $this->target_date->isPast() &&
            $this->status !== 'completed';
    }
}
