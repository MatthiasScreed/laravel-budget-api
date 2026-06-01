<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * Modèle DailyAction — Action quotidienne du MVP.
 * Remplace Transaction : saisie en < 30 secondes, pas de catégorie complexe.
 * Deux types : save (économie) ou spend (dépense).
 */
class DailyAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quest_id',
        'type',
        'amount',
        'reason',
        'reason_preset',
        'xp_earned',
        'action_date',
    ];

    protected $casts = [
        'amount'      => 'float',
        'xp_earned'   => 'integer',
        'action_date' => 'date',
    ];

    // XP par type d'action
    const XP_SAVE  = 10;
    const XP_SPEND = 3;

    // Raisons prédéfinies par type
    const PRESETS_SAVE = [
        'cooked'    => 'J\'ai cuisiné',
        'avoided'   => 'J\'ai évité un achat',
        'transport' => 'J\'ai pris les transports',
        'other_save'=> 'Autre économie',
    ];

    const PRESETS_SPEND = [
        'food'         => 'Nourriture / restaurant',
        'shopping'     => 'Shopping',
        'subscription' => 'Abonnement',
        'other_spend'  => 'Autre dépense',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeSaves($query)
    {
        return $query->where('type', 'save');
    }

    public function scopeSpends($query)
    {
        return $query->where('type', 'spend');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('action_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereYear('action_date', now()->year)
            ->whereMonth('action_date', now()->month);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Label lisible de la raison (preset ou texte libre)
     */
    public function getReasonLabelAttribute(): string
    {
        if ($this->reason_preset) {
            $allPresets = array_merge(self::PRESETS_SAVE, self::PRESETS_SPEND);
            return $allPresets[$this->reason_preset] ?? $this->reason ?? '';
        }

        return $this->reason ?? '';
    }

    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Calculer l'XP à attribuer selon le type
     */
    public static function calculateXp(string $type): int
    {
        return $type === 'save' ? self::XP_SAVE : self::XP_SPEND;
    }

    /**
     * L'utilisateur a-t-il déjà agi aujourd'hui ?
     */
    public static function hasActedToday(int $userId): bool
    {
        return self::where('user_id', $userId)
            ->whereDate('action_date', today())
            ->exists();
    }

    /**
     * Formatter les données pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'amount'       => $this->amount,
            'reason'       => $this->reason,
            'reason_preset'=> $this->reason_preset,
            'reason_label' => $this->reason_label,
            'xp_earned'    => $this->xp_earned,
            'quest_id'     => $this->quest_id,
            'action_date'  => $this->action_date->toDateString(),
            'created_at'   => $this->created_at->toISOString(),
        ];
    }
}
