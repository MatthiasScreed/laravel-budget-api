<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle Quest — Quête principale du MVP.
 * Une quête représente l'objectif financier motivant de l'utilisateur.
 * Ex: Voyage au Japon, MacBook Pro, Fonds d'urgence.
 */
class Quest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'emoji',
        'status',
        'is_main',
        'completed_at',
    ];

    protected $casts = [
        'target_amount'  => 'float',
        'current_amount' => 'float',
        'target_date'    => 'date',
        'is_main'        => 'boolean',
        'completed_at'   => 'datetime',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyActions(): HasMany
    {
        return $this->hasMany(DailyAction::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    // ==========================================
    // ACCESSORS / CALCULS
    // ==========================================

    /**
     * Pourcentage de progression (0-100)
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->current_amount / $this->target_amount) * 100, 1));
    }

    /**
     * Montant restant à atteindre
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->target_amount - $this->current_amount);
    }

    /**
     * Nombre de jours restants avant la date cible
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->target_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->target_date, false));
    }

    /**
     * La quête est-elle terminée ?
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed'
            || $this->current_amount >= $this->target_amount;
    }

    // ==========================================
    // MÉTHODES MÉTIER
    // ==========================================

    /**
     * Ajouter un montant à la quête (économie)
     */
    public function addAmount(float $amount): void
    {
        $this->increment('current_amount', $amount);
        $this->checkCompletion();
    }

    /**
     * Retirer un montant de la quête (dépense)
     */
    public function subtractAmount(float $amount): void
    {
        $newAmount = max(0, $this->current_amount - $amount);
        $this->update(['current_amount' => $newAmount]);
    }

    /**
     * Vérifier si la quête vient d'être complétée
     */
    private function checkCompletion(): void
    {
        $this->refresh();

        if ($this->current_amount >= $this->target_amount && $this->status === 'active') {
            $this->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Formatter les données pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'emoji'               => $this->emoji,
            'target_amount'       => $this->target_amount,
            'current_amount'      => $this->current_amount,
            'remaining_amount'    => $this->remaining_amount,
            'progress_percentage' => $this->progress_percentage,
            'target_date'         => $this->target_date?->toDateString(),
            'days_remaining'      => $this->days_remaining,
            'status'              => $this->status,
            'is_main'             => $this->is_main,
            'is_completed'        => $this->is_completed,
            'completed_at'        => $this->completed_at?->toISOString(),
            'created_at'          => $this->created_at->toISOString(),
        ];
    }
}
