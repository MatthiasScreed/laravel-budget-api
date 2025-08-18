<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'bank_name',
        'bank_code',
        'account_number_encrypted', // IBAN crypté
        'account_type',
        'connection_id', // ID du provider (Bridge, Budget Insight)
        'access_token_encrypted',
        'refresh_token_encrypted',
        'provider',
        'last_sync_at',
        'status',
        'error_count',
        'last_error',
        'auto_sync_enabled',
        'sync_frequency_hours'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'sync_frequency_hours' => 'integer',
        'error_count' => 'integer'
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
        'account_number_encrypted'
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ERROR = 'error';
    public const STATUS_DISABLED = 'disabled';

    // Provider constants
    public const PROVIDER_BRIDGE = 'bridge';
    public const PROVIDER_BUDGET_INSIGHT = 'budget_insight';
    public const PROVIDER_NORDIGEN = 'nordigen';

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions importées depuis cette connexion
     */
    public function importedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'bank_connection_id');
    }

    /**
     * Vérifier si la connexion est active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Vérifier si la synchronisation est due
     */
    public function needsSync(): bool
    {
        if (!$this->auto_sync_enabled || !$this->isActive()) {
            return false;
        }

        if (!$this->last_sync_at) {
            return true;
        }

        $hoursSinceLastSync = $this->last_sync_at->diffInHours(now());
        return $hoursSinceLastSync >= $this->sync_frequency_hours;
    }

    /**
     * Marquer une erreur de synchronisation
     */
    public function markSyncError(string $error): void
    {
        $this->increment('error_count');
        $this->update([
            'last_error' => $error,
            'status' => $this->error_count >= 5 ? self::STATUS_ERROR : $this->status
        ]);
    }

    /**
     * Marquer une synchronisation réussie
     */
    public function markSyncSuccess(): void
    {
        $this->update([
            'last_sync_at' => now(),
            'error_count' => 0,
            'last_error' => null,
            'status' => self::STATUS_ACTIVE
        ]);
    }
}
