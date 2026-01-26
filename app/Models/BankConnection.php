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
        'provider',
        'provider_connection_id',  // ✅ CHANGÉ : connection_id → provider_connection_id
        'bank_name',
        'bank_code',
        'bank_logo_url',
        'account_number_encrypted',
        'account_type',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'status',
        'is_active',
        'last_sync_at',
        'last_successful_sync_at',
        'last_error',
        'last_error_at',
        'error_count',
        'auto_sync_enabled',
        'sync_frequency_hours',
        'expires_at',
        'consent_expires_at',
        'disconnected_at',
        'metadata',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_successful_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
        'expires_at' => 'datetime',
        'consent_expires_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'is_active' => 'boolean',
        'sync_frequency_hours' => 'integer',
        'error_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
        'account_number_encrypted',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_DISCONNECTED = 'disconnected';

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
     * Bank transactions (import brut)
     */
    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Vérifier si la connexion est active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->is_active === true;
    }

    /**
     * Vérifier si la synchronisation est due
     */
    public function needsSync(): bool
    {
        if (! $this->auto_sync_enabled || ! $this->isActive()) {
            return false;
        }

        if (! $this->last_sync_at) {
            return true;
        }

        $hoursSinceLastSync = $this->last_sync_at->diffInHours(now());

        return $hoursSinceLastSync >= ($this->sync_frequency_hours ?? 24);
    }

    /**
     * Marquer une erreur de synchronisation
     */
    public function markSyncError(string $error): void
    {
        $this->increment('error_count');
        $this->update([
            'last_error' => $error,
            'last_error_at' => now(),
            'status' => $this->error_count >= 5
                ? self::STATUS_ERROR
                : $this->status,
        ]);
    }

    /**
     * Marquer une synchronisation réussie
     */
    public function markSyncSuccess(): void
    {
        $this->update([
            'last_sync_at' => now(),
            'last_successful_sync_at' => now(),
            'error_count' => 0,
            'last_error' => null,
            'last_error_at' => null,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Nombre de transactions importées
     */
    public function getTransactionsCountAttribute(): int
    {
        return $this->importedTransactions()->count();
    }

    // app/Models/BankConnection.php

    public function accounts()
    {
        return $this->hasMany(BankAccount::class, 'bank_connection_id');
    }
}
