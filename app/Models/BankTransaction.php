<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_connection_id',
        'external_id',
        'amount',
        'description',
        'transaction_date',
        'value_date',
        'account_balance_after',  // ✅ C'est dans ta table
        'merchant_name',
        'merchant_category',
        'raw_data',  // ✅ Pas "metadata" !
        'processing_status',
        'suggested_category_id',
        'confidence_score',
        'converted_transaction_id',
        'imported_at',
        'categorized_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'imported_at' => 'datetime',
        'categorized_at' => 'datetime',
        'confidence_score' => 'float',
        'raw_data' => 'array',  // ✅ JSON → Array
    ];

    // Status de traitement
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_CATEGORIZED = 'categorized';

    public const STATUS_CONVERTED = 'converted'; // Convertie en Transaction

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_DUPLICATE = 'duplicate';

    /**
     * Connexion bancaire associée
     */
    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    /**
     * Catégorie suggérée par l'IA
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    /**
     * Transaction finale créée (si convertie)
     */
    public function convertedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'converted_transaction_id');
    }

    /**
     * Vérifier si c'est un revenu (montant positif)
     */
    public function isIncome(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Vérifier si c'est une dépense (montant négatif)
     */
    public function isExpense(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Obtenir le montant absolu
     */
    public function getAbsoluteAmount(): float
    {
        return abs($this->amount);
    }

    /**
     * Formater la description pour l'affichage
     */
    public function getFormattedDescription(): string
    {
        // Nettoyer et formater la description bancaire souvent peu lisible
        $description = $this->description;

        // Si on a un nom de marchand, l'utiliser
        if ($this->merchant_name) {
            return $this->merchant_name;
        }

        // Nettoyer les codes bancaires inutiles
        $description = preg_replace('/^(VIR|PRLV|CB|ACHAT)\s*/', '', $description);
        $description = preg_replace('/\s+/', ' ', $description);

        return trim($description);
    }

    /**
     * Vérifier si déjà convertie en Transaction
     */
    public function isConverted(): bool
    {
        return $this->processing_status === self::STATUS_CONVERTED;
    }
}
