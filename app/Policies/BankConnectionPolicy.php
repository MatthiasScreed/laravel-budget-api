<?php

namespace App\Policies;

use App\Models\BankConnection;
use App\Models\User;

/**
 * Policy pour autoriser les actions sur BankConnection
 * École 42 compliant: fonctions ≤25 lignes, ≤5 paramètres
 */
class BankConnectionPolicy
{
    /**
     * L'utilisateur peut-il voir cette connexion ?
     */
    public function view(User $user, BankConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }

    /**
     * L'utilisateur peut-il modifier cette connexion ?
     */
    public function update(User $user, BankConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }

    /**
     * L'utilisateur peut-il supprimer cette connexion ?
     */
    public function delete(User $user, BankConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }

    /**
     * L'utilisateur peut-il synchroniser cette connexion ?
     * ✅ MÉTHODE REQUISE par BankController::sync()
     */
    public function sync(User $user, BankConnection $connection): bool
    {
        return $user->id === $connection->user_id
            && $connection->status === 'active';
    }

    /**
     * L'utilisateur peut-il gérer cette connexion ?
     * ✅ MÉTHODE REQUISE par BankController::destroy()
     */
    public function manage(User $user, BankConnection $connection): bool
    {
        return $user->id === $connection->user_id;
    }
}
