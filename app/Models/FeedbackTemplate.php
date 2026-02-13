<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'trigger_event',
        'category',
        'title',
        'message',
        'icon',
        'conditions',
        'engagement_level',
        'tone',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // CONSTANTES - Événements déclencheurs
    // ==========================================

    const EVENT_TRANSACTION_CREATED = 'transaction_created';
    const EVENT_TRANSACTION_INCOME = 'transaction_income';
    const EVENT_GOAL_CREATED = 'goal_created';
    const EVENT_GOAL_PROGRESS = 'goal_progress';
    const EVENT_GOAL_COMPLETED = 'goal_completed';
    const EVENT_SAVINGS_POSITIVE = 'savings_positive';
    const EVENT_STREAK_CONTINUED = 'streak_continued';
    const EVENT_STREAK_BROKEN = 'streak_broken';
    const EVENT_WEEKLY_SUMMARY = 'weekly_summary';
    const EVENT_MILESTONE_REACHED = 'milestone_reached';
    const EVENT_FIRST_LOGIN = 'first_login';
    const EVENT_RETURN_AFTER_ABSENCE = 'return_after_absence';

    // ==========================================
    // CONSTANTES - Catégories
    // ==========================================

    const CATEGORY_ENCOURAGEMENT = 'encouragement';
    const CATEGORY_CELEBRATION = 'celebration';
    const CATEGORY_TIP = 'tip';
    const CATEGORY_NUDGE = 'nudge';
    const CATEGORY_INSIGHT = 'insight';

    // ==========================================
    // CONSTANTES - Tons
    // ==========================================

    const TONE_NEUTRAL = 'neutral';
    const TONE_ENCOURAGING = 'encouraging';
    const TONE_CELEBRATORY = 'celebratory';
    const TONE_INFORMATIVE = 'informative';

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('trigger_event', $event);
    }

    public function scopeForEngagementLevel($query, int $level)
    {
        return $query->where('engagement_level', '<=', $level);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // ==========================================
    // RELATIONS
    // ==========================================

    public function feedbackLogs(): HasMany
    {
        return $this->hasMany(UserFeedbackLog::class);
    }

    // ==========================================
    // MÉTHODES
    // ==========================================

    /**
     * Vérifie si les conditions sont remplies
     */
    public function matchesConditions(array $context): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $key => $expectedValue) {
            $actualValue = $context[$key] ?? null;

            if ($actualValue === null) {
                return false;
            }

            // Comparaison selon le type
            if (is_numeric($expectedValue)) {
                if (str_starts_with($key, 'min_')) {
                    if ($actualValue < $expectedValue) return false;
                } elseif (str_starts_with($key, 'max_')) {
                    if ($actualValue > $expectedValue) return false;
                } else {
                    if ($actualValue != $expectedValue) return false;
                }
            } else {
                if ($actualValue !== $expectedValue) return false;
            }
        }

        return true;
    }

    /**
     * Remplace les variables dans le message
     */
    public function renderMessage(array $context): string
    {
        $message = $this->message;

        // Remplacer les variables {{ variable }}
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $message, $matches);

        foreach ($matches[1] as $index => $variable) {
            $value = $context[$variable] ?? '';

            // Formatage automatique des nombres
            if (is_numeric($value)) {
                $value = number_format($value, 2, ',', ' ');
            }

            $message = str_replace($matches[0][$index], $value, $message);
        }

        return $message;
    }

    /**
     * Génère le feedback complet
     */
    public function generateFeedback(array $context): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->renderMessage($context),
            'icon' => $this->icon,
            'category' => $this->category,
            'tone' => $this->tone,
        ];
    }

    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Trouve le meilleur feedback pour un événement
     */
    public static function findBestMatch(
        string $event,
        int $engagementLevel,
        array $context = []
    ): ?self {
        $candidates = self::active()
            ->forEvent($event)
            ->forEngagementLevel($engagementLevel)
            ->byPriority()
            ->get();

        foreach ($candidates as $template) {
            if ($template->matchesConditions($context)) {
                return $template;
            }
        }

        return null;
    }
}
