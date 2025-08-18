<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'data', 'channel'
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'clicked_at' => 'datetime'
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Méthodes utilitaires
    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }

    public function markAsClicked(): bool
    {
        return $this->update(['clicked_at' => now()]);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    // Factory method pour créer différents types de notifications
    public static function createXpGained(int $userId, int $xp, string $reason): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'xp_gain',
            'title' => "🎯 +{$xp} XP Gagné !",
            'body' => $reason,
            'data' => ['xp_amount' => $xp, 'reason' => $reason],
            'channel' => 'web'
        ]);
    }

    public static function createAchievementUnlocked(int $userId, array $achievement): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'achievement',
            'title' => "🏆 Achievement Débloqué !",
            'body' => "Badge '{$achievement['name']}' obtenu !",
            'data' => ['achievement' => $achievement],
            'channel' => 'web'
        ]);
    }

    public static function createLevelUp(int $userId, int $oldLevel, int $newLevel): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'level_up',
            'title' => "📈 Niveau Supérieur !",
            'body' => "Niveau {$newLevel} atteint ! Nouvelles fonctionnalités débloquées",
            'data' => ['old_level' => $oldLevel, 'new_level' => $newLevel],
            'channel' => 'web'
        ]);
    }
}
