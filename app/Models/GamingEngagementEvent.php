<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamingEngagementEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'element_type',
        'element_id',
        'affinity_impact',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ==========================================
    // CONSTANTES - Types d'événements
    // ==========================================

    const TYPE_CLICKED_ACHIEVEMENT = 'clicked_achievement';
    const TYPE_VIEWED_LEADERBOARD = 'viewed_leaderboard';
    const TYPE_JOINED_CHALLENGE = 'joined_challenge';
    const TYPE_CHECKED_XP = 'checked_xp';
    const TYPE_DISMISSED_XP_POPUP = 'dismissed_xp_popup';
    const TYPE_DISMISSED_ACHIEVEMENT = 'dismissed_achievement';
    const TYPE_SHARED_ACHIEVEMENT = 'shared_achievement';
    const TYPE_VIEWED_GAMING_STATS = 'viewed_gaming_stats';

    // Impact sur l'affinité
    const IMPACT_SCORES = [
        self::TYPE_CLICKED_ACHIEVEMENT => 5,
        self::TYPE_VIEWED_LEADERBOARD => 3,
        self::TYPE_JOINED_CHALLENGE => 8,
        self::TYPE_CHECKED_XP => 2,
        self::TYPE_DISMISSED_XP_POPUP => -5,
        self::TYPE_DISMISSED_ACHIEVEMENT => -3,
        self::TYPE_SHARED_ACHIEVEMENT => 10,
        self::TYPE_VIEWED_GAMING_STATS => 4,
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Enregistre un événement d'engagement
     */
    public static function record(
        User $user,
        string $eventType,
        ?string $elementType = null,
        ?int $elementId = null,
        array $metadata = []
    ): self {
        $impact = self::IMPACT_SCORES[$eventType] ?? 0;

        $event = self::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'element_type' => $elementType,
            'element_id' => $elementId,
            'affinity_impact' => $impact,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        // Mettre à jour le profil gaming
        $profile = UserGamingProfile::getOrCreate($user);
        $profile->recordInteraction($impact > 0);

        // Recalculer périodiquement l'affinité
        if ($profile->total_interactions % 10 === 0) {
            $profile->recalculateAffinityScore();
            $profile->updateEngagementLevel();
        }

        return $event;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopePositive($query)
    {
        return $query->where('affinity_impact', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('affinity_impact', '<', 0);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
