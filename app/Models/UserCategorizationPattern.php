<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle UserCategorizationPattern
 *
 * Représente un pattern de catégorisation appris
 * depuis les corrections utilisateur
 *
 * @property int $id
 * @property int $user_id
 * @property string $pattern
 * @property int $category_id
 * @property int $match_count
 * @property float $confidence
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UserCategorizationPattern extends Model
{
    use HasFactory;

    /**
     * Table associée
     */
    protected $table = 'user_categorization_patterns';

    /**
     * Attributs mass assignable
     */
    protected $fillable = [
        'user_id',
        'pattern',
        'category_id',
        'match_count',
        'confidence',
    ];

    /**
     * Attributs castés
     */
    protected $casts = [
        'match_count' => 'integer',
        'confidence' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation : appartient à un utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation : appartient à une catégorie
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope : patterns avec haute confiance
     */
    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    /**
     * Scope : patterns fréquents
     */
    public function scopeFrequent($query, int $minMatches = 3)
    {
        return $query->where('match_count', '>=', $minMatches);
    }

    /**
     * Scope : par utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Incrémenter le compteur de matches
     */
    public function incrementMatch(): void
    {
        $this->increment('match_count');

        // Augmenter confiance (max 0.95)
        $newConfidence = min($this->confidence + 0.05, 0.95);
        $this->update(['confidence' => $newConfidence]);
    }

    /**
     * Obtenir patterns les plus utilisés pour un user
     */
    public static function topPatternsForUser(int $userId, int $limit = 10): \Illuminate\Support\Collection
    {
        return static::forUser($userId)
            ->with('category')
            ->orderBy('match_count', 'desc')
            ->orderBy('confidence', 'desc')
            ->limit($limit)
            ->get();
    }
}
