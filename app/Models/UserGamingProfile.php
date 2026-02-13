<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGamingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'engagement_level',
        'gaming_affinity_score',
        'gaming_preference',
        'total_interactions',
        'gaming_interactions',
        'dismissed_gaming_elements',
        'last_gaming_interaction',
        'unlocked_features',
        'show_xp_notifications',
        'show_level_badges',
        'show_leaderboard',
        'show_challenges',
    ];

    protected $casts = [
        'unlocked_features' => 'array',
        'last_gaming_interaction' => 'datetime',
        'show_xp_notifications' => 'boolean',
        'show_level_badges' => 'boolean',
        'show_leaderboard' => 'boolean',
        'show_challenges' => 'boolean',
    ];

    // ==========================================
    // CONSTANTES - Niveaux d'engagement
    // ==========================================

    const LEVEL_SOFT = 1;      // Encouragements doux
    const LEVEL_REWARDS = 2;   // Points et paliers visibles
    const LEVEL_SOCIAL = 3;    // Comparaisons et streaks
    const LEVEL_GAMING = 4;    // Full gaming (leaderboards, défis)

    const LEVEL_LABELS = [
        self::LEVEL_SOFT => 'Encouragements',
        self::LEVEL_REWARDS => 'Récompenses',
        self::LEVEL_SOCIAL => 'Social',
        self::LEVEL_GAMING => 'Gaming complet',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function engagementEvents(): HasMany
    {
        return $this->hasMany(GamingEngagementEvent::class, 'user_id', 'user_id');
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Niveau d'engagement effectif (prend en compte la préférence)
     */
    public function getEffectiveEngagementLevelAttribute(): int
    {
        if ($this->gaming_preference === 'auto') {
            return $this->engagement_level;
        }

        return match ($this->gaming_preference) {
            'minimal' => self::LEVEL_SOFT,
            'moderate' => self::LEVEL_REWARDS,
            'full' => self::LEVEL_GAMING,
            default => $this->engagement_level,
        };
    }

    /**
     * Label du niveau d'engagement
     */
    public function getEngagementLabelAttribute(): string
    {
        return self::LEVEL_LABELS[$this->effective_engagement_level] ?? 'Inconnu';
    }

    /**
     * Ratio d'engagement gaming
     */
    public function getGamingEngagementRatioAttribute(): float
    {
        if ($this->total_interactions === 0) {
            return 0;
        }

        return round($this->gaming_interactions / $this->total_interactions, 2);
    }

    // ==========================================
    // MÉTHODES - Vérification des fonctionnalités
    // ==========================================

    /**
     * Vérifie si une fonctionnalité est débloquée
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->unlocked_features ?? [];
        return in_array($feature, $features);
    }

    /**
     * Débloque une fonctionnalité
     */
    public function unlockFeature(string $feature): void
    {
        $features = $this->unlocked_features ?? [];

        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->update(['unlocked_features' => $features]);
        }
    }

    /**
     * Vérifie si l'XP doit être affiché
     */
    public function shouldShowXP(): bool
    {
        if ($this->gaming_preference === 'minimal') {
            return false;
        }

        return $this->effective_engagement_level >= self::LEVEL_REWARDS
            || $this->show_xp_notifications;
    }

    /**
     * Vérifie si les badges de niveau doivent être affichés
     */
    public function shouldShowLevelBadges(): bool
    {
        if ($this->gaming_preference === 'minimal') {
            return false;
        }

        return $this->effective_engagement_level >= self::LEVEL_REWARDS
            || $this->show_level_badges;
    }

    /**
     * Vérifie si le leaderboard doit être affiché
     */
    public function shouldShowLeaderboard(): bool
    {
        if ($this->gaming_preference !== 'full' && $this->gaming_preference !== 'auto') {
            return false;
        }

        return $this->effective_engagement_level >= self::LEVEL_SOCIAL
            || $this->show_leaderboard;
    }

    /**
     * Vérifie si les défis doivent être affichés
     */
    public function shouldShowChallenges(): bool
    {
        return $this->effective_engagement_level >= self::LEVEL_GAMING
            || $this->show_challenges;
    }

    // ==========================================
    // MÉTHODES - Mise à jour de l'engagement
    // ==========================================

    /**
     * Enregistre une interaction
     */
    public function recordInteraction(bool $isGamingRelated = false): void
    {
        $updates = ['total_interactions' => $this->total_interactions + 1];

        if ($isGamingRelated) {
            $updates['gaming_interactions'] = $this->gaming_interactions + 1;
            $updates['last_gaming_interaction'] = now();
        }

        $this->update($updates);
    }

    /**
     * Enregistre un élément gaming ignoré/fermé
     */
    public function recordDismissedElement(): void
    {
        $this->increment('dismissed_gaming_elements');
    }

    /**
     * Recalcule le score d'affinité gaming
     */
    public function recalculateAffinityScore(): int
    {
        $score = 50; // Score de base neutre

        // Ratio d'interactions gaming (+/- 20 points)
        $ratio = $this->gaming_engagement_ratio;
        $score += (int) (($ratio - 0.5) * 40);

        // Pénalité pour éléments dismissés (-2 par élément, max -20)
        $dismissPenalty = min(20, $this->dismissed_gaming_elements * 2);
        $score -= $dismissPenalty;

        // Bonus pour interactions récentes (+10 si actif cette semaine)
        if ($this->last_gaming_interaction?->isAfter(now()->subWeek())) {
            $score += 10;
        }

        // Borner entre 0 et 100
        $score = max(0, min(100, $score));

        $this->update(['gaming_affinity_score' => $score]);

        return $score;
    }

    /**
     * Met à jour le niveau d'engagement basé sur l'affinité
     */
    public function updateEngagementLevel(): int
    {
        if ($this->gaming_preference !== 'auto') {
            return $this->effective_engagement_level;
        }

        $score = $this->gaming_affinity_score;
        $interactions = $this->total_interactions;

        // Progression basée sur le score ET l'ancienneté
        $newLevel = match (true) {
            $score >= 70 && $interactions >= 100 => self::LEVEL_GAMING,
            $score >= 50 && $interactions >= 50 => self::LEVEL_SOCIAL,
            $score >= 30 && $interactions >= 20 => self::LEVEL_REWARDS,
            default => self::LEVEL_SOFT,
        };

        if ($newLevel !== $this->engagement_level) {
            $this->update(['engagement_level' => $newLevel]);
        }

        return $newLevel;
    }

    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Crée ou récupère le profil gaming d'un utilisateur
     */
    public static function getOrCreate(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'engagement_level' => self::LEVEL_SOFT,
                'gaming_affinity_score' => 50,
                'unlocked_features' => ['basic_feedback'],
            ]
        );
    }
}
