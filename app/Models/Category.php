<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'color',
        'icon',
        'is_active',
        'is_system',
        'sort_order',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $attributes = [
        'type' => 'expense',
        'color' => '#6B7280',
        'is_active' => true,
        'is_system' => false,
        'sort_order' => 0,
    ];

    /**
     * Les types de catégories disponibles
     */
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_INCOME => 'Revenus',
        self::TYPE_EXPENSE => 'Dépenses',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereNull('user_id'); // Inclure les catégories globales
        });
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les catégories actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les catégories système
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope pour les catégories utilisateur
     */
    public function scopeUserDefined($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope pour les catégories globales
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope pour ordonner par ordre d'affichage
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope pour les revenus
     */
    public function scopeIncome($query)
    {
        return $query->ofType(self::TYPE_INCOME);
    }

    /**
     * Scope pour les dépenses
     */
    public function scopeExpense($query)
    {
        return $query->ofType(self::TYPE_EXPENSE);
    }

    /**
     * Accessor pour le nom du type
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor pour vérifier si c'est une catégorie globale
     */
    public function getIsGlobalAttribute(): bool
    {
        return is_null($this->user_id);
    }

    /**
     * Mutator pour la couleur (validation format hexadécimal)
     */
    public function setColorAttribute($value)
    {
        if ($value && ! preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            $value = '#6B7280'; // Couleur par défaut si format invalide
        }
        $this->attributes['color'] = $value;
    }

    /**
     * Vérifier si la catégorie appartient à un utilisateur
     */
    public function belongsToUser($userId): bool
    {
        return $this->user_id == $userId || $this->is_global;
    }

    /**
     * Vérifier si la catégorie peut être modifiée
     */
    public function isEditable(): bool
    {
        return ! $this->is_system;
    }

    /**
     * Vérifier si la catégorie peut être supprimée
     */
    public function isDeletable(): bool
    {
        return ! $this->is_system && $this->transactions()->count() === 0;
    }

    /**
     * Obtenir le nombre total de transactions
     */
    public function getTransactionsCountAttribute(): int
    {
        return $this->transactions()->count();
    }

    /**
     * Obtenir le montant total des transactions
     */
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->transactions()->sum('amount');
    }

    /**
     * Boot method pour gérer les événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Empêcher la suppression des catégories système
        static::deleting(function ($category) {
            if ($category->is_system) {
                throw new \Exception('Les catégories système ne peuvent pas être supprimées.');
            }
        });
    }
}
