<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'type',
        'criteria',
        'points',
        'rarity',
        'is_active'
    ];

    protected $casts = [
        'criteria' => 'array',
        'points' => 'integer',
        'is_active' => 'boolean'
    ];

    protected $attributes = [
        'color' => '#3B82F6',
        'rarity' => 'common',
        'is_active' => true
    ];

    /**
     * Types de succès
     */
    public const TYPE_TRANSACTION = 'transaction';
    public const TYPE_GOAL = 'goal';
    public const TYPE_STREAK = 'streak';
    public const TYPE_MILESTONE = 'milestone';
    public const TYPE_SOCIAL = 'social';

    public const TYPES = [
        self::TYPE_TRANSACTION => 'Transaction',
        self::TYPE_GOAL => 'Objectif',
        self::TYPE_STREAK => 'Série',
        self::TYPE_MILESTONE => 'Étape',
        self::TYPE_SOCIAL => 'Social'
    ];

    /**
     * Niveaux de rareté
     */
    public const RARITY_COMMON = 'common';
    public const RARITY_RARE = 'rare';
    public const RARITY_EPIC = 'epic';
    public const RARITY_LEGENDARY = 'legendary';

    public const RARITIES = [
        self::RARITY_COMMON => 'Commun',
        self::RARITY_RARE => 'Rare',
        self::RARITY_EPIC => 'Épique',
        self::RARITY_LEGENDARY => 'Légendaire'
    ];

    /**
     * Couleurs par rareté
     */
    public const RARITY_COLORS = [
        self::RARITY_COMMON => '#6B7280',
        self::RARITY_RARE => '#3B82F6',
        self::RARITY_EPIC => '#8B5CF6',
        self::RARITY_LEGENDARY => '#F59E0B'
    ];

    /**
     * Relation avec les utilisateurs qui ont débloqué ce succès
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['unlocked_at', 'metadata'])
            ->withTimestamps();
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope pour les succès actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour filtrer par rareté
     */
    public function scopeOfRarity($query, $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Accessor pour le nom du type
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor pour le nom de la rareté
     */
    public function getRarityNameAttribute(): string
    {
        return self::RARITIES[$this->rarity] ?? $this->rarity;
    }

    /**
     * Accessor pour la couleur de rareté
     */
    public function getRarityColorAttribute(): string
    {
        return self::RARITY_COLORS[$this->rarity] ?? $this->color;
    }

    // ==========================================
    // MÉTHODES MÉTIER
    // ==========================================

    /**
     * Vérifier si un utilisateur a débloqué ce succès
     *
     * @param User $user Utilisateur à vérifier
     * @return bool Succès débloqué ou non
     */
    public function isUnlockedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Débloquer le succès pour un utilisateur
     *
     * @param User $user Utilisateur concerné
     * @param array $metadata Données additionnelles
     * @return bool Déblocage réussi
     */
    public function unlockFor(User $user, array $metadata = []): bool
    {
        if ($this->isUnlockedBy($user)) {
            return false; // Déjà débloqué
        }

        // Version simplifiée sans metadata pour éviter les erreurs
        $this->users()->attach($user->id, [
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->grantXpToUser($user);

        return true;
    }

    /**
     * Attacher l'utilisateur avec métadonnées
     *
     * @param User $user Utilisateur concerné
     * @param array $metadata Métadonnées
     */
    protected function attachUserWithMetadata(User $user, array $metadata): void
    {
        $this->users()->attach($user->id, [
            'unlocked_at' => now(),
            'metadata' => empty($metadata) ? null : json_encode($metadata), // Conversion en JSON
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Accorder les points XP à l'utilisateur
     *
     * @param User $user Utilisateur concerné
     */
    protected function grantXpToUser(User $user): void
    {
        if ($this->points > 0) {
            $user->addXp($this->points);
        }
    }


    /**
     * Vérifier si les critères sont remplis pour un utilisateur
     *
     * @param User $user Utilisateur à vérifier
     * @return bool Critères remplis
     */
    public function checkCriteria(User $user): bool
    {
        if (!$this->is_active || !$this->criteria) {
            return false;
        }

        return match($this->type) {
            self::TYPE_TRANSACTION => $this->checkTransactionCriteria($user),
            self::TYPE_GOAL => $this->checkGoalCriteria($user),
            self::TYPE_MILESTONE => $this->checkMilestoneCriteria($user),
            default => false // Types pas encore implémentés
        };
    }

    /**
     * Vérifier les critères de transaction
     *
     * @param User $user Utilisateur concerné
     * @return bool Critères validés
     */
    protected function checkTransactionCriteria(User $user): bool
    {
        $criteria = $this->criteria;

        if (isset($criteria['min_transactions'])) {
            $count = $user->transactions()->where('status', 'completed')->count();
            return $count >= $criteria['min_transactions'];
        }

        if (isset($criteria['min_amount'])) {
            $total = $user->transactions()->where('status', 'completed')->sum('amount');
            return $total >= $criteria['min_amount'];
        }

        return false;
    }

    /**
     * Vérifier les critères d'objectif
     *
     * @param User $user Utilisateur concerné
     * @return bool Critères validés
     */
    protected function checkGoalCriteria(User $user): bool
    {
        $criteria = $this->criteria;

        if (isset($criteria['min_goals_completed'])) {
            $count = $user->financialGoals()->where('status', 'completed')->count();
            return $count >= $criteria['min_goals_completed'];
        }

        if (isset($criteria['min_savings_amount'])) {
            $total = $user->getTotalSavings();
            return $total >= $criteria['min_savings_amount'];
        }

        return false;
    }

    /**
     * Vérifier les critères d'étape
     *
     * @param User $user Utilisateur concerné
     * @return bool Critères validés
     */
    protected function checkMilestoneCriteria(User $user): bool
    {
        $criteria = $this->criteria;

        if (isset($criteria['financial_health_score'])) {
            $score = $user->getFinancialHealthScore();
            return $score >= $criteria['financial_health_score'];
        }

        if (isset($criteria['min_level'])) {
            $level = $user->getCurrentLevel();
            return $level >= $criteria['min_level'];
        }

        return false;
    }


    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Créer les succès par défaut du système
     */
    public static function createDefaults(): void
    {
        $achievements = self::getDefaultAchievements();

        foreach ($achievements as $achievement) {
            self::updateOrCreate(
                ['slug' => $achievement['slug']],
                $achievement
            );
        }
    }

    /**
     * Obtenir la liste des succès par défaut
     *
     * @return array Liste des succès
     */
    protected static function getDefaultAchievements(): array
    {
        return [
            // Succès de transaction
            [
                'name' => 'Premier pas',
                'slug' => 'first-transaction',
                'description' => 'Enregistrer votre première transaction',
                'icon' => 'play-circle',
                'type' => self::TYPE_TRANSACTION,
                'criteria' => ['min_transactions' => 1],
                'points' => 10,
                'rarity' => self::RARITY_COMMON
            ],
            [
                'name' => 'Actif',
                'slug' => 'active-user',
                'description' => 'Enregistrer 10 transactions',
                'icon' => 'activity',
                'type' => self::TYPE_TRANSACTION,
                'criteria' => ['min_transactions' => 10],
                'points' => 25,
                'rarity' => self::RARITY_COMMON
            ],
            [
                'name' => 'Expert comptable',
                'slug' => 'expert-accountant',
                'description' => 'Enregistrer 100 transactions',
                'icon' => 'trending-up',
                'type' => self::TYPE_TRANSACTION,
                'criteria' => ['min_transactions' => 100],
                'points' => 100,
                'rarity' => self::RARITY_EPIC
            ],

            // Succès d'objectifs
            [
                'name' => 'Planificateur',
                'slug' => 'planner',
                'description' => 'Créer votre premier objectif financier',
                'icon' => 'target',
                'type' => self::TYPE_GOAL,
                'criteria' => ['min_goals_created' => 1],
                'points' => 15,
                'rarity' => self::RARITY_COMMON
            ],
            [
                'name' => 'Réalisateur',
                'slug' => 'achiever',
                'description' => 'Atteindre votre premier objectif',
                'icon' => 'check-circle',
                'type' => self::TYPE_GOAL,
                'criteria' => ['min_goals_completed' => 1],
                'points' => 50,
                'rarity' => self::RARITY_RARE
            ],

            // Succès d'étapes
            [
                'name' => 'Épargnant débutant',
                'slug' => 'beginner-saver',
                'description' => 'Épargner 1000€ au total',
                'icon' => 'piggy-bank',
                'type' => self::TYPE_MILESTONE,
                'criteria' => ['min_savings_amount' => 1000],
                'points' => 30,
                'rarity' => self::RARITY_COMMON
            ],
            [
                'name' => 'Montée en grade',
                'slug' => 'level-up',
                'description' => 'Atteindre le niveau 5',
                'icon' => 'arrow-up',
                'type' => self::TYPE_MILESTONE,
                'criteria' => ['min_level' => 5],
                'points' => 25,
                'rarity' => self::RARITY_COMMON
            ]
        ];
    }
}
