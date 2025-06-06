<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Streak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'current_count',
        'best_count',
        'last_activity_date',
        'is_active'
    ];

    protected $casts = [
        'current_count' => 'integer',
        'best_count' => 'integer',
        'last_activity_date' => 'date',
        'is_active' => 'boolean'
    ];

    protected $attributes = [
        'current_count' => 0,
        'best_count' => 0,
        'is_active' => true
    ];

    /**
     * Types de séries
     */
    public const TYPE_DAILY_TRANSACTION = 'daily_transaction';
    public const TYPE_WEEKLY_GOAL = 'weekly_goal';
    public const TYPE_MONTHLY_SAVING = 'monthly_saving';

    public const TYPES = [
        self::TYPE_DAILY_TRANSACTION => 'Transaction quotidienne',
        self::TYPE_WEEKLY_GOAL => 'Objectif hebdomadaire',
        self::TYPE_MONTHLY_SAVING => 'Épargne mensuelle'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour les séries actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Incrémenter la série
     */
    public function increment(): bool
    {
        $today = now()->toDateString();

        // Vérifier si déjà fait aujourd'hui
        if ($this->last_activity_date && $this->last_activity_date->toDateString() === $today) {
            return false; // Déjà fait aujourd'hui
        }

        // Vérifier si la série continue
        $yesterday = now()->subDay()->toDateString();
        if (!$this->last_activity_date || $this->last_activity_date->toDateString() === $yesterday) {
            $this->current_count++;
        } else {
            // Série brisée, recommencer
            $this->current_count = 1;
        }

        // Mettre à jour le meilleur score
        if ($this->current_count > $this->best_count) {
            $this->best_count = $this->current_count;
        }

        $this->last_activity_date = now();
        $this->save();

        return true;
    }

    /**
     * Vérifier si la série est brisée
     */
    public function checkIfBroken(): bool
    {
        if (!$this->last_activity_date) {
            return false;
        }

        $daysSinceLastActivity = now()->diffInDays($this->last_activity_date);

        if ($daysSinceLastActivity > 1) {
            $this->current_count = 0;
            $this->is_active = false;
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Réactiver la série
     */
    public function reactivate(): void
    {
        $this->is_active = true;
        $this->current_count = 0;
        $this->save();
    }


}
