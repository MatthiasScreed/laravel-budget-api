<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'type',
        'difficulty',
        'criteria',
        'reward_xp',
        'reward_items',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'reward_items' => 'array',
        'reward_xp' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'difficulty' => 'medium',
        'is_active' => true,
    ];

    /**
     * Types de défis
     */
    public const TYPE_PERSONAL = 'personal';

    public const TYPE_COMMUNITY = 'community';

    public const TYPE_SEASONAL = 'seasonal';

    public const TYPES = [
        self::TYPE_PERSONAL => 'Personnel',
        self::TYPE_COMMUNITY => 'Communauté',
        self::TYPE_SEASONAL => 'Saisonnier',
    ];

    /**
     * Niveaux de difficulté
     */
    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTY_EXPERT = 'expert';

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY => 'Facile',
        self::DIFFICULTY_MEDIUM => 'Moyen',
        self::DIFFICULTY_HARD => 'Difficile',
        self::DIFFICULTY_EXPERT => 'Expert',
    ];

    /**
     * Relation avec les utilisateurs participants
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_challenges')
            ->withPivot(['status', 'progress_percentage', 'progress_data', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Scope pour les défis actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Vérifier si le défi est disponible
     */
    public function isAvailable(): bool
    {
        $now = now();

        return $this->is_active &&
            $this->start_date <= $now &&
            $this->end_date >= $now;
    }

    /**
     * Faire participer un utilisateur au défi
     */
    public function addParticipant(User $user): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if ($this->users()->where('user_id', $user->id)->exists()) {
            return false; // Déjà participant
        }

        $this->users()->attach($user->id, [
            'status' => 'active',
            'progress_percentage' => 0,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Mettre à jour la progression d'un utilisateur
     */
    public function updateUserProgress(User $user, float $progressPercentage, array $progressData = []): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'progress_percentage' => min(100, $progressPercentage),
            'progress_data' => $progressData,
            'updated_at' => now(),
        ]);

        // Vérifier si le défi est terminé
        if ($progressPercentage >= 100) {
            $this->completeForUser($user);
        }
    }

    /**
     * Marquer le défi comme terminé pour un utilisateur
     */
    public function completeForUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        // Donner les récompenses
        $user->addXp($this->reward_xp);
    }
}
