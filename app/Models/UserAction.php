<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserAction extends Model
{
    public $timestamps = false; // On utilise seulement created_at

    protected $fillable = [
        'user_id', 'action_type', 'context', 'metadata', 'xp_gained'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes pour queries fréquentes
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeByActionType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    // Méthodes utilitaires
    public static function getXpForAction(string $actionType): int
    {
        return match($actionType) {
            'page_view' => 1,
            'button_click' => 2,
            'transaction_add' => 15,
            'goal_create' => 50,
            'goal_contribute' => 10,
            'achievement_view' => 3,
            'share_success' => 25,
            'daily_login' => 5,
            default => 1
        };
    }

    public static function trackAction(int $userId, string $actionType, ?string $context = null, ?array $metadata = null): self
    {
        $xp = self::getXpForAction($actionType);

        return self::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'context' => $context,
            'metadata' => $metadata,
            'xp_gained' => $xp
        ]);
    }
}
