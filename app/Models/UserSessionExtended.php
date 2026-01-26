<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSessionExtended extends Model
{
    use HasFactory;

    protected $table = 'user_sessions_extended';

    protected $fillable = [
        'user_id',
        'session_id',
        'token_id',
        'started_at',
        'ended_at',
        'actions_count',
        'xp_earned',
        'pages_visited',
        'device_type',
        'device_name',
        'ip_address',
        'user_agent',
        'device_info',
        'is_current',
        'last_activity_at',
        'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'pages_visited' => 'array',
        'device_info' => 'array',
        'is_current' => 'boolean',
        'actions_count' => 'integer',
        'xp_earned' => 'integer',
    ];

    // ==========================================
    // RELATIONS
    // ==========================================

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // MÉTHODES UTILITAIRES
    // ==========================================

    /**
     * Marquer la session comme terminée
     */
    public function endSession(): void
    {
        $this->update([
            'ended_at' => now(),
            'is_current' => false,
        ]);
    }

    /**
     * Ajouter une action à la session
     */
    public function addAction(string $actionType, int $xpGained = 0): void
    {
        $this->increment('actions_count');

        if ($xpGained > 0) {
            $this->increment('xp_earned', $xpGained);
        }

        $this->touch('last_activity_at');
    }

    /**
     * Ajouter une page visitée
     */
    public function addPageVisit(string $page): void
    {
        $pages = $this->pages_visited ?? [];

        if (! in_array($page, $pages)) {
            $pages[] = $page;
            $this->update(['pages_visited' => $pages]);
        }
    }

    /**
     * Calculer la durée de la session
     */
    public function getDurationInMinutes(): int
    {
        $end = $this->ended_at ?? now();

        return $this->started_at->diffInMinutes($end);
    }

    /**
     * Vérifier si la session est active
     */
    public function isActive(): bool
    {
        return $this->is_current &&
            is_null($this->ended_at) &&
            ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Obtenir le type d'appareil formaté
     */
    public function getFormattedDeviceType(): string
    {
        return match ($this->device_type) {
            'mobile' => '📱 Mobile',
            'tablet' => '📊 Tablette',
            'desktop' => '🖥️ Bureau',
            default => '❓ Inconnu'
        };
    }

    /**
     * Obtenir les statistiques de la session
     */
    public function getSessionStats(): array
    {
        return [
            'duration_minutes' => $this->getDurationInMinutes(),
            'actions_count' => $this->actions_count,
            'xp_earned' => $this->xp_earned,
            'pages_count' => count($this->pages_visited ?? []),
            'xp_per_minute' => $this->getDurationInMinutes() > 0
                ? round($this->xp_earned / $this->getDurationInMinutes(), 2)
                : 0,
        ];
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope pour les sessions actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_current', true)
            ->whereNull('ended_at');
    }

    /**
     * Scope pour les sessions d'un utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour les sessions d'un type d'appareil
     */
    public function scopeByDeviceType($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope pour les sessions récentes
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    // ==========================================
    // MÉTHODES STATIQUES
    // ==========================================

    /**
     * Créer une nouvelle session étendue
     */
    public static function createFromRequest(Request $request, $token): self
    {
        return self::create([
            'user_id' => $request->user()->id,
            'session_id' => $request->session()->getId(),
            'token_id' => $token->id,
            'started_at' => now(),
            'actions_count' => 0,
            'xp_earned' => 0,
            'pages_visited' => [],
            'device_type' => self::detectDeviceType($request),
            'device_name' => $token->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_info' => self::parseUserAgent($request->userAgent()),
            'is_current' => true,
            'last_activity_at' => now(),
            'expires_at' => $token->expires_at,
        ]);
    }

    /**
     * Détecter le type d'appareil
     */
    protected static function detectDeviceType(Request $request): string
    {
        $userAgent = strtolower($request->userAgent());

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Parser le User-Agent pour extraire les infos
     */
    protected static function parseUserAgent(string $userAgent): array
    {
        $info = [
            'browser' => 'Unknown',
            'platform' => 'Unknown',
            'device' => 'Unknown',
            'version' => 'Unknown',
        ];

        // Détection du navigateur
        if (preg_match('/Chrome\/([0-9\.]+)/', $userAgent, $matches)) {
            $info['browser'] = 'Chrome';
            $info['version'] = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/', $userAgent, $matches)) {
            $info['browser'] = 'Firefox';
            $info['version'] = $matches[1];
        } elseif (preg_match('/Safari\/([0-9\.]+)/', $userAgent, $matches)) {
            $info['browser'] = 'Safari';
            $info['version'] = $matches[1];
        }

        // Détection du système
        if (str_contains($userAgent, 'Windows')) {
            $info['platform'] = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $info['platform'] = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $info['platform'] = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $info['platform'] = 'Android';
        } elseif (str_contains($userAgent, 'iOS')) {
            $info['platform'] = 'iOS';
        }

        return $info;
    }

    /**
     * Nettoyer les sessions inactives
     */
    public static function cleanupInactiveSessions(): int
    {
        $deleted = self::where('is_current', true)
            ->where('last_activity_at', '<', now()->subHours(2))
            ->update([
                'is_current' => false,
                'ended_at' => now(),
            ]);

        return $deleted;
    }
}
