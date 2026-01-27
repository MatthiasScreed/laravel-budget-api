<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'color',
        'type',
        'categories',
        'default_duration_months',
        'tips',
        'milestones',
        'min_amount',
        'max_amount',
        'popularity_score',
        'is_active',
        'is_premium',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'tips' => 'array',
            'milestones' => 'array',
            'metadata' => 'array',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'is_premium' => 'boolean',
        ];
    }

    /**
     * Relation avec les projets utilisateurs
     */
    public function userProjects(): HasMany
    {
        return $this->hasMany(UserProject::class, 'template_key', 'key');
    }

    /**
     * Scope pour les templates actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les templates gratuits
     */
    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    /**
     * Scope pour les templates populaires
     */
    public function scopePopular($query)
    {
        return $query->orderBy('popularity_score', 'desc');
    }

    /**
     * Scope par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
