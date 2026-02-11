<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\SimpleCategorizer;

class TransactionObserver
{
    /**
     * Auto-catégoriser à la création
     */
    public function creating(Transaction $transaction): void
    {
        if (!$transaction->category_id) {
            $categorizer = app(SimpleCategorizer::class);
            $category = $categorizer->categorize($transaction);

            if ($category) {
                $transaction->category_id = $category->id;
            }
        }
    }
}
