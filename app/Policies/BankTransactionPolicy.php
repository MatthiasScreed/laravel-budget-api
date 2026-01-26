<?php

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\User;

/**
 * Policy pour autoriser les actions sur BankTransaction
 * École 42 compliant: fonctions ≤25 lignes, ≤5 paramètres
 */
class BankTransactionPolicy
{
    /**
     * L'utilisateur peut-il voir cette transaction ?
     */
    public function view(User $user, BankTransaction $transaction): bool
    {
        return $transaction->bankConnection
            && $transaction->bankConnection->user_id === $user->id;
    }

    /**
     * L'utilisateur peut-il gérer cette transaction ?
     * ✅ MÉTHODE REQUISE par BankController
     */
    public function manageBankTransaction(
        User $user,
        BankTransaction $transaction
    ): bool {
        return $transaction->bankConnection
            && $transaction->bankConnection->user_id === $user->id;
    }

    /**
     * L'utilisateur peut-il convertir cette transaction ?
     */
    public function convert(User $user, BankTransaction $transaction): bool
    {
        return $this->manageBankTransaction($user, $transaction)
            && in_array($transaction->processing_status, [
                'imported',
                'categorized',
            ]);
    }

    /**
     * L'utilisateur peut-il ignorer cette transaction ?
     */
    public function ignore(User $user, BankTransaction $transaction): bool
    {
        return $this->manageBankTransaction($user, $transaction);
    }
}
