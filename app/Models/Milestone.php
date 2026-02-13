<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'category',
        'title',
        'description',
        'icon',
        'conditions',
        'points_reward',
        'feature_unlock',
        'rewards',
        'min_engagement_level',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'rewards' => 'array',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // CONSTANTES - Catégories
    // ==========================================

    const CATEGORY_FINANCIAL = 'financial';
    const CATEGORY_ENGAGEMENT = 'engagement';
    const CATEGORY_STREAK = 'streak';
    const CATEGORY_SOCIAL = 'social';
    const CATEGORY_DISCOVERY = 'discovery';

    const CATEGORIES = [
        self::CATEGORY_FINANCIAL => ['label' => 'Finances', 'icon' => '💰'],
        self::CATEGORY_ENGAGEMENT => ['label' => 'Engagement', 'icon' => '⭐'],
        self::CATEGORY_STREAK => ['label' => 'Régularité', 'icon' => '🔥'],
        self::CATEGORY_SOCIAL => ['label' => 'Social', 'icon' => '👥'],
        self::CATEGORY_DISCOVERY => ['label' => 'Découverte', 'icon' => '🔍'],
    ];

    // ==========================================
    // CONSTANTES - Types de conditions
    // ==========================================

    const CONDITION_TRANSACTION_COUNT = 'transaction_count';
    const CONDITION_SAVINGS_AMOUNT = 'savings_amount';
    const CONDITION_SAVINGS_RATE = 'savings_rate';
    const CONDITION_GOAL_COMPLETED = 'goal_completed';
    const CONDITION_GOAL_PROGRESS = 'goal_progress';
    const CONDITION_CONSECUTIVE_DAYS = 'consecutive_days';
    const CONDITION_CATEGORY_BUDGET = 'category_budget_respected';
    const CONDITION_INCOME_RECORDED = 'income_recorded';
    const CONDITION_FEATURE_USED = 'feature_used';

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEngagementLevel($query, int $level)
    {
        return $query->where('min_engagement_level', '<=', $level);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ==========================================
    // RELATIONS
    // ==========================================

    public function userMilestones(): HasMany
    {
        return $this->hasMany(UserMilestone::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_milestones')
            ->withPivot(['progress', 'target', 'is_completed', 'completed_at'])
            ->withTimestamps();
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getCategoryInfoAttribute(): array
    {
        return self::CATEGORIES[$this->category] ?? [
            'label' => ucfirst($this->category),
            'icon' => '📌'
        ];
    }

    /**
     * Extrait la valeur cible des conditions
     */
    public function getTargetValueAttribute(): float
    {
        return $this->conditions['value'] ?? 1;
    }

    /**
     * Extrait le type de condition
     */
    public function getConditionTypeAttribute(): string
    {
        return $this->conditions['type'] ?? 'unknown';
    }

    // ==========================================
    // MÉTHODES
    // ==========================================

    /**
     * Évalue la progression d'un utilisateur pour ce milestone
     */
    public function evaluateProgress(User $user): array
    {
        $conditionType = $this->condition_type;
        $targetValue = $this->target_value;
        $currentValue = 0;

        switch ($conditionType) {
            case self::CONDITION_TRANSACTION_COUNT:
                $currentValue = $this->getTransactionCount($user);
                break;

            case self::CONDITION_SAVINGS_AMOUNT:
                $currentValue = $this->getSavingsAmount($user);
                break;

            case self::CONDITION_SAVINGS_RATE:
                $currentValue = $this->getSavingsRate($user);
                break;

            case self::CONDITION_GOAL_COMPLETED:
                $currentValue = $this->getCompletedGoalsCount($user);
                break;

            case self::CONDITION_CONSECUTIVE_DAYS:
                $currentValue = $this->getConsecutiveDays($user);
                break;

            case self::CONDITION_INCOME_RECORDED:
                $currentValue = $this->getTotalIncome($user);
                break;

            default:
                $currentValue = 0;
        }

        $progress = min(100, ($currentValue / $targetValue) * 100);
        $isCompleted = $currentValue >= $targetValue;

        return [
            'current_value' => $currentValue,
            'target_value' => $targetValue,
            'progress_percentage' => round($progress, 1),
            'is_completed' => $isCompleted,
        ];
    }

    // ==========================================
    // MÉTHODES PRIVÉES - Calculs de progression
    // ==========================================

    private function getTransactionCount(User $user): int
    {
        $query = $user->transactions();

        if (isset($this->conditions['period'])) {
            $query = $this->applyPeriodFilter($query, $this->conditions['period']);
        }

        if (isset($this->conditions['type'])) {
            $query->where('type', $this->conditions['type']);
        }

        return $query->count();
    }

    private function getSavingsAmount(User $user): float
    {
        $period = $this->conditions['period'] ?? 'all';

        $income = $user->transactions()
            ->where('type', 'income')
            ->when($period !== 'all', fn($q) => $this->applyPeriodFilter($q, $period))
            ->sum('amount');

        $expenses = $user->transactions()
            ->where('type', 'expense')
            ->when($period !== 'all', fn($q) => $this->applyPeriodFilter($q, $period))
            ->sum('amount');

        return max(0, $income - $expenses);
    }

    private function getSavingsRate(User $user): float
    {
        $period = $this->conditions['period'] ?? 'month';

        $income = $user->transactions()
            ->where('type', 'income')
            ->when(true, fn($q) => $this->applyPeriodFilter($q, $period))
            ->sum('amount');

        if ($income <= 0) {
            return 0;
        }

        $expenses = $user->transactions()
            ->where('type', 'expense')
            ->when(true, fn($q) => $this->applyPeriodFilter($q, $period))
            ->sum('amount');

        return round((($income - $expenses) / $income) * 100, 1);
    }

    private function getCompletedGoalsCount(User $user): int
    {
        return $user->financialGoals()
            ->whereNotNull('completed_at')
            ->count();
    }

    private function getConsecutiveDays(User $user): int
    {
        // Simplifié - à améliorer avec la vraie logique de streak
        $streak = $user->streaks()
            ->where('type', 'daily_login')
            ->where('is_active', true)
            ->first();

        return $streak?->current_count ?? 0;
    }

    private function getTotalIncome(User $user): float
    {
        $query = $user->transactions()->where('type', 'income');

        if (isset($this->conditions['period'])) {
            $query = $this->applyPeriodFilter($query, $this->conditions['period']);
        }

        return $query->sum('amount');
    }

    private function applyPeriodFilter($query, string $period)
    {
        return match ($period) {
            'day' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->startOfWeek()),
            'month' => $query->where('created_at', '>=', now()->startOfMonth()),
            'year' => $query->where('created_at', '>=', now()->startOfYear()),
            default => $query,
        };
    }
}
