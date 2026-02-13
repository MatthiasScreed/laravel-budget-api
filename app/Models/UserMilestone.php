<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'milestone_id',
        'progress',
        'target',
        'is_completed',
        'completed_at',
        'reward_claimed',
        'completion_context',
    ];

    protected $casts = [
        'progress' => 'decimal:2',
        'target' => 'decimal:2',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reward_claimed' => 'boolean',
        'completion_context' => 'array',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target <= 0) {
            return 0;
        }

        return min(100, round(($this->progress / $this->target) * 100, 1));
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->target - $this->progress);
    }

    // ==========================================
    // MÉTHODES
    // ==========================================

    /**
     * Met à jour la progression
     */
    public function updateProgress(float $newProgress, array $context = []): bool
    {
        $wasCompleted = $this->is_completed;
        $this->progress = $newProgress;

        if (!$wasCompleted && $newProgress >= $this->target) {
            $this->is_completed = true;
            $this->completed_at = now();
            $this->completion_context = $context;
        }

        $this->save();

        return !$wasCompleted && $this->is_completed; // Retourne true si vient d'être complété
    }

    /**
     * Réclame la récompense
     */
    public function claimReward(): array
    {
        if (!$this->is_completed || $this->reward_claimed) {
            return ['success' => false, 'message' => 'Récompense non disponible'];
        }

        $this->update(['reward_claimed' => true]);

        $milestone = $this->milestone;
        $rewards = [];

        // Points
        if ($milestone->points_reward > 0) {
            $rewards['points'] = $milestone->points_reward;
        }

        // Fonctionnalité débloquée
        if ($milestone->feature_unlock) {
            $rewards['feature'] = $milestone->feature_unlock;

            $profile = UserGamingProfile::getOrCreate($this->user);
            $profile->unlockFeature($milestone->feature_unlock);
        }

        // Récompenses additionnelles
        if ($milestone->rewards) {
            $rewards['extras'] = $milestone->rewards;
        }

        return ['success' => true, 'rewards' => $rewards];
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeRewardAvailable($query)
    {
        return $query->where('is_completed', true)
            ->where('reward_claimed', false);
    }
}
