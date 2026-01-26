<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'bank_connection_id',           // 🆕 Bridge
        'external_transaction_id',      // 🆕 Bridge
        'bridge_transaction_id',        // 🆕 Bridge
        'category_id',
        'type',
        'amount',
        'description',
        'transaction_date',
        'status',
        'reference',
        'payment_method',
        'metadata',
        'is_recurring',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_end_date',
        'parent_transaction_id',
        'is_reconciled',
        'reconciled_at',
        'is_transfer',
        'transfer_transaction_id',
        'source',
        'is_from_bridge',               // 🆕 Bridge
        'auto_imported',                // 🆕 Bridge
        'auto_categorized',             // 🆕 Bridge
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'recurrence_end_date' => 'date',
        'reconciled_at' => 'date',
        'is_recurring' => 'boolean',
        'is_reconciled' => 'boolean',
        'is_transfer' => 'boolean',
        'is_from_bridge' => 'boolean',     // 🆕 Bridge
        'auto_imported' => 'boolean',      // 🆕 Bridge
        'auto_categorized' => 'boolean',   // 🆕 Bridge
        'recurrence_interval' => 'integer',
        'metadata' => 'array',
    ];

    protected $dates = [
        'transaction_date',
        'recurrence_end_date',
        'reconciled_at',
        'deleted_at',
    ];

    protected $attributes = [
        'status' => 'completed',
        'is_recurring' => false,
        'recurrence_interval' => 1,
        'is_reconciled' => false,
        'is_transfer' => false,
        'source' => 'manual',
        'is_from_bridge' => false,      // 🆕 Bridge
        'auto_imported' => false,       // 🆕 Bridge
        'auto_categorized' => false,    // 🆕 Bridge
    ];

    /**
     * Les types de transactions
     */
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_INCOME => 'Revenus',
        self::TYPE_EXPENSE => 'Dépenses',
    ];

    /**
     * Les statuts de transactions
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_COMPLETED => 'Terminée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    /**
     * Les types de récurrence
     */
    public const RECURRENCE_DAILY = 'daily';

    public const RECURRENCE_WEEKLY = 'weekly';

    public const RECURRENCE_MONTHLY = 'monthly';

    public const RECURRENCE_YEARLY = 'yearly';

    public const RECURRENCE_TYPES = [
        self::RECURRENCE_DAILY => 'Quotidienne',
        self::RECURRENCE_WEEKLY => 'Hebdomadaire',
        self::RECURRENCE_MONTHLY => 'Mensuelle',
        self::RECURRENCE_YEARLY => 'Annuelle',
    ];

    /**
     * Les méthodes de paiement communes
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'check' => 'Chèque',
        'transfer' => 'Virement',
        'online' => 'Paiement en ligne',
        'other' => 'Autre',
    ];

    /**
     * 🆕 Sources de transactions
     */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BRIDGE = 'bridge';

    public const SOURCE_RECURRING = 'recurring';

    public const SOURCE_IMPORT = 'import';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Manuelle',
        self::SOURCE_BRIDGE => 'Import bancaire',
        self::SOURCE_RECURRING => 'Récurrence',
        self::SOURCE_IMPORT => 'Import fichier',
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

    /**
     * Relation avec la catégorie
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * 🆕 Relation avec la connexion bancaire
     */
    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    /**
     * Relation avec la transaction parente (pour récurrences)
     */
    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    /**
     * Relation avec les transactions enfants (récurrences)
     */
    public function childTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    /**
     * Relation avec la transaction de transfert liée
     */
    public function transferTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transfer_transaction_id');
    }

    /**
     * Relation avec les contributions aux objectifs
     */
    public function goalContributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
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
     * Scope pour filtrer par statut
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les transactions terminées
     */
    public function scopeCompleted($query)
    {
        return $query->withStatus(self::STATUS_COMPLETED);
    }

    /**
     * Scope pour les transactions en attente
     */
    public function scopePending($query)
    {
        return $query->withStatus(self::STATUS_PENDING);
    }

    /**
     * Scope pour filtrer par période
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope pour les transactions récurrentes
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope pour les transactions rapprochées
     */
    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    /**
     * Scope pour les transferts
     */
    public function scopeTransfers($query)
    {
        return $query->where('is_transfer', true);
    }

    /**
     * Scope pour filtrer par catégorie
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope pour ordonner par date
     */
    public function scopeOrdered($query, $direction = 'desc')
    {
        return $query->orderBy('transaction_date', $direction)
            ->orderBy('created_at', $direction);
    }

    /**
     * 🆕 Scope pour les transactions Bridge
     */
    public function scopeFromBridge($query)
    {
        return $query->where('is_from_bridge', true);
    }

    /**
     * 🆕 Scope pour les transactions sans catégorie
     */
    public function scopeUncategorized($query)
    {
        return $query->whereNull('category_id');
    }

    /**
     * 🆕 Scope pour les transactions auto-catégorisées
     */
    public function scopeAutoCategorized($query)
    {
        return $query->where('auto_categorized', true);
    }

    /**
     * 🆕 Scope pour les transactions manuelles
     */
    public function scopeManual($query)
    {
        return $query->where('source', self::SOURCE_MANUAL);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Accessor pour le nom du type
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Accessor pour le nom du statut
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor pour le nom de la récurrence
     */
    public function getRecurrenceTypeNameAttribute(): ?string
    {
        return $this->recurrence_type ?
            (self::RECURRENCE_TYPES[$this->recurrence_type] ?? $this->recurrence_type) :
            null;
    }

    /**
     * Accessor pour le montant formaté
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->type === self::TYPE_EXPENSE ? '-' : '+';

        return $sign.number_format($this->amount, 2, ',', ' ').' €';
    }

    /**
     * Accessor pour vérifier si la transaction est modifiable
     */
    public function getIsEditableAttribute(): bool
    {
        return $this->status !== self::STATUS_CANCELLED &&
            ! $this->is_reconciled &&
            $this->source === self::SOURCE_MANUAL;
    }

    /**
     * 🆕 Accessor pour le nom de la source
     */
    public function getSourceNameAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    /**
     * 🆕 Accessor pour vérifier si c'est une transaction Bridge
     */
    public function getIsBridgeTransactionAttribute(): bool
    {
        return $this->is_from_bridge || $this->source === self::SOURCE_BRIDGE;
    }

    // ==========================================
    // MÉTHODES MÉTIER
    // ==========================================

    /**
     * Vérifier si la transaction appartient à un utilisateur
     */
    public function belongsToUser($userId): bool
    {
        return $this->user_id == $userId;
    }

    /**
     * Marquer comme rapprochée
     */
    public function markAsReconciled(): bool
    {
        return $this->update([
            'is_reconciled' => true,
            'reconciled_at' => now(),
        ]);
    }

    /**
     * Annuler le rapprochement
     */
    public function unreconcile(): bool
    {
        return $this->update([
            'is_reconciled' => false,
            'reconciled_at' => null,
        ]);
    }

    /**
     * 🆕 Vérifier si la transaction nécessite une catégorisation
     */
    public function needsCategorization(): bool
    {
        return is_null($this->category_id) &&
            $this->status === self::STATUS_PENDING;
    }

    /**
     * 🆕 Marquer comme catégorisée automatiquement
     */
    public function markAsAutoCategorized($categoryId): bool
    {
        return $this->update([
            'category_id' => $categoryId,
            'auto_categorized' => true,
            'status' => self::STATUS_COMPLETED,
        ]);
    }

    /**
     * Générer la prochaine transaction récurrente
     */
    public function generateNextRecurrence(): ?Transaction
    {
        if (! $this->is_recurring || ! $this->recurrence_type) {
            return null;
        }

        $nextDate = $this->calculateNextRecurrenceDate();

        if (! $nextDate || ($this->recurrence_end_date && $nextDate->gt($this->recurrence_end_date))) {
            return null;
        }

        return self::create([
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'transaction_date' => $nextDate,
            'status' => self::STATUS_PENDING,
            'payment_method' => $this->payment_method,
            'parent_transaction_id' => $this->id,
            'source' => self::SOURCE_RECURRING,
        ]);
    }

    /**
     * Calculer la prochaine date de récurrence
     */
    private function calculateNextRecurrenceDate(): ?Carbon
    {
        $lastDate = $this->childTransactions()
            ->orderBy('transaction_date', 'desc')
            ->first()?->transaction_date ?? $this->transaction_date;

        return match ($this->recurrence_type) {
            self::RECURRENCE_DAILY => $lastDate->addDays($this->recurrence_interval),
            self::RECURRENCE_WEEKLY => $lastDate->addWeeks($this->recurrence_interval),
            self::RECURRENCE_MONTHLY => $lastDate->addMonths($this->recurrence_interval),
            self::RECURRENCE_YEARLY => $lastDate->addYears($this->recurrence_interval),
            default => null
        };
    }

    // ==========================================
    // BOOT METHOD
    // ==========================================

    /**
     * Boot method pour gérer les événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Validation avant sauvegarde
        static::saving(function ($transaction) {
            // Valider la cohérence des données de récurrence
            if ($transaction->is_recurring && ! $transaction->recurrence_type) {
                throw new \Exception('Le type de récurrence est requis pour une transaction récurrente.');
            }

            // 🆕 Auto-définir source pour transactions Bridge
            if ($transaction->is_from_bridge && $transaction->source === self::SOURCE_MANUAL) {
                $transaction->source = self::SOURCE_BRIDGE;
            }

            // 🆕 Auto-définir is_from_bridge si source est bridge
            if ($transaction->source === self::SOURCE_BRIDGE && ! $transaction->is_from_bridge) {
                $transaction->is_from_bridge = true;
            }
        });
    }
}
