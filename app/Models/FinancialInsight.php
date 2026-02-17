<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'priority',
        'title',
        'description',
        'icon',
        'action_label',
        'action_data',
        'potential_saving',
        'goal_impact',
        'metadata',
        'is_read',
        'is_dismissed',
        'acted_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'goal_impact' => 'array',
        'metadata' => 'array',
        'is_read' => 'boolean',
        'is_dismissed' => 'boolean',
        'acted_at' => 'datetime',
        'potential_saving' => 'decimal:2',
    ];

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Marquer comme lu
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Marquer l'action comme effectuée
     */
    public function markAsActed(): void
    {
        $this->update(['acted_at' => now()]);
    }

    /**
     * Rejeter l'insight
     */
    public function dismiss(): void
    {
        $this->update(['is_dismissed' => true]);
    }
}
