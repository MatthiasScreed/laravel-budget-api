<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedbackLog extends Model
{
use HasFactory;

protected $table = 'user_feedback_log';

protected $fillable = [
'user_id',
'feedback_template_id',
'trigger_event',
'context',
'user_reaction',
'reacted_at',
];

protected $casts = [
'context' => 'array',
'reacted_at' => 'datetime',
];

// ==========================================
// RELATIONS
// ==========================================

public function user(): BelongsTo
{
return $this->belongsTo(User::class);
}

public function template(): BelongsTo
{
return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
}

// ==========================================
// MÉTHODES
// ==========================================

/**
* Enregistre la réaction de l'utilisateur
*/
public function recordReaction(string $reaction): void
{
$this->update([
'user_reaction' => $reaction,
'reacted_at' => now(),
]);

// Mettre à jour le profil gaming selon la réaction
$profile = UserGamingProfile::getOrCreate($this->user);

if ($reaction === 'dismissed') {
$profile->recordDismissedElement();
} elseif ($reaction === 'clicked') {
$profile->recordInteraction(true);
}
}

// ==========================================
// SCOPES
// ==========================================

public function scopeRecent($query, int $days = 7)
{
return $query->where('created_at', '>=', now()->subDays($days));
}

public function scopeForEvent($query, string $event)
{
return $query->where('trigger_event', $event);
}
}
