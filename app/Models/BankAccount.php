<?php

// app/Models/BankAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'bank_connection_id',
        'external_id',
        'account_name',
        'account_type',
        'balance',
        'currency',
        'iban',
        'account_number',
        'is_active',
        'last_balance_update',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'float',
        'is_active' => 'boolean',
        'last_balance_update' => 'datetime',
        'metadata' => 'array',
    ];

    public function bankConnection()
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class, 'account_id', 'external_id');
    }
}
