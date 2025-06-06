<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'date_of_birth',
        'currency',
        'timezone',
        'language',
        'email_verified_at',
        'preferences'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'preferences' => 'array'
        ];
    }

    /**
     * The attributes with default values.
     */
    protected $attributes = [
        'currency' => 'EUR',
        'timezone' => 'Europe/Paris',
        'language' => 'fr'
    ];


    // ==========================================
    // RELATIONS
    // ==========================================

    /**
     * Les transactions de l'utilisateur
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Les catégories de l'utilisateur
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Les objectifs financiers de l'utilisateur
     */
    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    /**
     * Alias pour les objectifs financiers
     */
    public function goals(): HasMany
    {
        return $this->financialGoals();
    }

    /**
     * Les suggestions pour l'utilisateur
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /**
     * Les contributions aux objectifs via les objectifs financiers
     */
    public function goalContributions(): HasManyThrough
    {
        return $this->hasManyThrough(
            GoalContribution::class,
            FinancialGoal::class,
            'user_id',        // Clé étrangère sur financial_goals
            'financial_goal_id', // Clé étrangère sur goal_contributions
            'id',             // Clé locale sur users
            'id'              // Clé locale sur financial_goals
        );
    }

    /**
     * Les projections via les objectifs financiers
     */
    public function projections(): HasManyThrough
    {
        return $this->hasManyThrough(
            Projection::class,
            FinancialGoal::class,
            'user_id',
            'financial_goal_id',
            'id',
            'id'
        );
    }

    // ==========================================
    // RELATIONS GAMING (ÉTAPE 1 - NIVEAUX)
    // ==========================================

    /**
     * Le niveau de l'utilisateur
     */
    public function level(): HasOne
    {
        return $this->hasOne(UserLevel::class);
    }

    /**
     * Les succès débloqués par l'utilisateur
     */
    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot(['unlocked_at'])  // Supprimer 'metadata'
            ->withTimestamps();
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Utilisateurs actifs (non supprimés)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Utilisateurs avec email vérifié
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Utilisateurs par devise
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * URL complète de l'avatar
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }

        // Avatar par défaut avec initiales
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=3B82F6&color=ffffff';
    }

    /**
     * Prénom (premier mot du nom)
     */
    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name)[0];
    }

    /**
     * Age de l'utilisateur
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Vérifier si l'email est vérifié
     */
    public function getIsEmailVerifiedAttribute(): bool
    {
        return !is_null($this->email_verified_at);
    }

    // ==========================================
    // MÉTHODES MÉTIER
    // ==========================================

    /**
     * Obtenir le solde total actuel
     */
    public function getTotalBalance(): float
    {
        $income = $this->transactions()->income()->completed()->sum('amount');
        $expenses = $this->transactions()->expense()->completed()->sum('amount');

        return $income - $expenses;
    }

    /**
     * Obtenir les revenus du mois
     */
    public function getMonthlyIncome(?Carbon $month = null): float
    {
        $month = $month ?? now();

        return $this->transactions()
            ->income()
            ->completed()
            ->whereYear('transaction_date', $month->year)
            ->whereMonth('transaction_date', $month->month)
            ->sum('amount');
    }

    /**
     * Obtenir les dépenses du mois
     */
    public function getMonthlyExpenses(?Carbon $month = null): float
    {
        $month = $month ?? now();

        return $this->transactions()
            ->expense()
            ->completed()
            ->whereYear('transaction_date', $month->year)
            ->whereMonth('transaction_date', $month->month)
            ->sum('amount');
    }

    /**
     * Obtenir l'épargne totale (contributions aux objectifs)
     */
    public function getTotalSavings(): float
    {
        return $this->goalContributions()->sum('amount');
    }

    /**
     * Obtenir les objectifs actifs
     */
    public function getActiveGoals()
    {
        return $this->financialGoals()->active()->get();
    }

    /**
     * Obtenir les suggestions non lues
     */
    public function getUnreadSuggestions()
    {
        return $this->suggestions()
            ->pending()
            ->valid()
            ->orderedByPriority()
            ->get();
    }

    /**
     * Obtenir les catégories les plus utilisées
     */
    public function getTopCategories($limit = 5)
    {
        return $this->categories()
            ->withCount('transactions')
            ->orderBy('transactions_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Statistiques financières de l'utilisateur
     */
    public function getFinancialStats(): array
    {
        $currentMonth = now();
        $previousMonth = now()->subMonth();

        return [
            'total_balance' => $this->getTotalBalance(),
            'current_month' => [
                'income' => $this->getMonthlyIncome($currentMonth),
                'expenses' => $this->getMonthlyExpenses($currentMonth),
                'savings' => $this->getMonthlyIncome($currentMonth) - $this->getMonthlyExpenses($currentMonth)
            ],
            'previous_month' => [
                'income' => $this->getMonthlyIncome($previousMonth),
                'expenses' => $this->getMonthlyExpenses($previousMonth),
                'savings' => $this->getMonthlyIncome($previousMonth) - $this->getMonthlyExpenses($previousMonth)
            ],
            'total_savings' => $this->getTotalSavings(),
            'active_goals_count' => $this->financialGoals()->active()->count(),
            'completed_goals_count' => $this->financialGoals()->completed()->count(),
            'categories_count' => $this->categories()->active()->count(),
            'transactions_count' => $this->transactions()->count()
        ];
    }

    /**
     * Obtenir une préférence utilisateur
     */
    public function getPreference(string $key, $default = null)
    {
        $preferences = $this->preferences ?? [];
        return $preferences[$key] ?? $default;
    }

    /**
     * Définir une préférence utilisateur
     */
    public function setPreference(string $key, $value): bool
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;

        return $this->update(['preferences' => $preferences]);
    }

    /**
     * Vérifier si l'utilisateur a des données financières
     */
    public function hasFinancialData(): bool
    {
        return $this->transactions()->exists() ||
            $this->financialGoals()->exists() ||
            $this->categories()->exists();
    }

    /**
     * Obtenir le score de santé financière (0-100)
     */
    public function getFinancialHealthScore(): int
    {
        $score = 0;

        // Bonus pour avoir des catégories (organisation)
        if ($this->categories()->count() >= 3) $score += 20;

        // Bonus pour avoir des objectifs financiers
        if ($this->financialGoals()->active()->count() >= 1) $score += 25;

        // Bonus pour épargne régulière
        $monthlyIncome = $this->getMonthlyIncome();
        $monthlyExpenses = $this->getMonthlyExpenses();
        if ($monthlyIncome > $monthlyExpenses) $score += 30;

        // Bonus pour diversité des transactions
        if ($this->transactions()->count() >= 10) $score += 15;

        // Bonus pour contributions régulières aux objectifs
        if ($this->goalContributions()->count() >= 3) $score += 10;

        return min(100, $score);
    }

    /**
     * Réinitialiser le token d'API actuel
     */
    public function refreshApiToken(): string
    {
        $this->tokens()->delete();
        return $this->createToken('auth_token')->plainTextToken;
    }

    // ==========================================
    // MÉTHODES GAMING (ÉTAPE 1 - NIVEAUX)
    // ==========================================

    /**
     * Ajouter de l'XP à l'utilisateur
     *
     * @param int $xp Points d'expérience à ajouter
     * @return array Résultat de l'ajout d'XP
     */
    public function addXp(int $xp): array
    {
        $userLevel = $this->level ?? $this->createUserLevel();
        return $userLevel->addXp($xp);
    }

    /**
     * Créer le niveau utilisateur s'il n'existe pas
     *
     * @return UserLevel Niveau créé
     */
    protected function createUserLevel(): UserLevel
    {
        return $this->level()->create();
    }

    /**
     * Obtenir le niveau actuel
     *
     * @return int Niveau actuel
     */
    public function getCurrentLevel(): int
    {
        return $this->level?->level ?? 1;
    }

    /**
     * Obtenir l'XP total
     *
     * @return int XP total
     */
    public function getTotalXp(): int
    {
        return $this->level?->total_xp ?? 0;
    }

    /**
     * Obtenir le titre basé sur le niveau
     *
     * @return string Titre utilisateur
     */
    public function getTitle(): string
    {
        return $this->level?->getTitle() ?? 'Débutant';
    }

    /**
     * Vérifier et débloquer les succès automatiquement
     *
     * @return array Succès débloqués
     */
    public function checkAndUnlockAchievements(): array
    {
        $unlockedAchievements = [];

        $availableAchievements = Achievement::active()
            ->whereNotIn('id', $this->achievements()->pluck('achievement_id'))
            ->get();

        foreach ($availableAchievements as $achievement) {
            if ($achievement->checkCriteria($this)) {
                if ($achievement->unlockFor($this)) {
                    $unlockedAchievements[] = $achievement;
                }
            }
        }

        return $unlockedAchievements;
    }

    /**
     * Obtenir les succès récents
     *
     * @param int $limit Nombre maximum de succès
     * @return Collection Succès récents
     */
    public function getRecentAchievements(int $limit = 5)
    {
        return $this->achievements()
            ->orderBy('user_achievements.unlocked_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtenir les statistiques gaming mises à jour
     *
     * @return array Stats gaming complètes
     */
    public function getGamingStats(): array
    {
        $levelStats = $this->level?->getDetailedStats() ?? [
            'current_level' => 1,
            'total_xp' => 0,
            'progress_percentage' => 0,
            'title' => 'Débutant'
        ];

        return [
            'level_info' => $levelStats,
            'achievements_count' => $this->achievements()->count(),
            'recent_achievements' => $this->getRecentAchievements(3),
            'active_challenges' => 0,  // Sera mis à jour plus tard
            'completed_challenges' => 0, // Sera mis à jour plus tard
            'best_streak' => 0 // Sera mis à jour plus tard
        ];
    }

    // ==========================================
    // ÉVÉNEMENTS UTILISATEUR (ÉTAPE 1)
    // ==========================================

    /**
     * Boot method pour la création automatique du niveau
     */
    protected static function boot()
    {
        parent::boot();

        // Créer automatiquement le niveau lors de la création d'un utilisateur
        static::created(function ($user) {
            $user->level()->create();
        });
    }


}
