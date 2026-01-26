<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service pour catégoriser automatiquement les transactions Bridge
 *
 * À CRÉER DANS: app/Services/BridgeCategorizationService.php
 *
 * École 42 Standards: ✅
 * - Fonctions ≤ 25 lignes
 * - Max 5 paramètres
 * - Commentaires clairs
 */
class BridgeCategorizationService
{
    /**
     * Catégoriser automatiquement une transaction Bridge
     */
    public function categorizeBridgeTransaction(
        Transaction $transaction
    ): bool {
        if ($transaction->category_id) {
            return true; // Déjà catégorisée
        }

        $category = $this->findBestCategory($transaction);

        if (! $category) {
            $category = $this->createDefaultCategory($transaction);
        }

        return $transaction->update([
            'category_id' => $category->id,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    /**
     * Trouver la meilleure catégorie pour une transaction
     */
    private function findBestCategory(
        Transaction $transaction
    ): ?Category {
        $user = $transaction->user;
        $description = strtolower($transaction->description);

        // 1. Chercher dans l'historique
        $historicalMatch = $this->findHistoricalMatch(
            $user,
            $description,
            $transaction->type
        );

        if ($historicalMatch) {
            return $historicalMatch;
        }

        // 2. Chercher par mots-clés
        return $this->findByKeywords(
            $user,
            $description,
            $transaction->type
        );
    }

    /**
     * Trouver une correspondance dans l'historique
     */
    private function findHistoricalMatch(
        User $user,
        string $description,
        string $type
    ): ?Category {
        $similar = $user->transactions()
            ->whereNotNull('category_id')
            ->where('type', $type)
            ->get();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($similar as $trans) {
            $score = $this->similarity(
                $description,
                strtolower($trans->description)
            );

            if ($score > $bestScore && $score >= 0.6) {
                $bestScore = $score;
                $bestMatch = $trans->category;
            }
        }

        return $bestMatch;
    }

    /**
     * Trouver une catégorie par mots-clés
     */
    private function findByKeywords(
        User $user,
        string $description,
        string $type
    ): ?Category {
        $keywords = $this->getKeywordMappings();
        $categories = $user->categories()
            ->where('type', $type)
            ->get();

        foreach ($keywords as $categoryName => $words) {
            foreach ($words as $word) {
                if (str_contains($description, $word)) {
                    $category = $categories->firstWhere(
                        'name',
                        'like',
                        "%{$categoryName}%"
                    );

                    if ($category) {
                        return $category;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Créer une catégorie par défaut
     */
    private function createDefaultCategory(
        Transaction $transaction
    ): Category {
        $user = $transaction->user;
        $type = $transaction->type;

        // Nom de catégorie par défaut
        $name = $type === 'income'
            ? 'Autres Revenus'
            : 'Autres Dépenses';

        // Vérifier si la catégorie existe déjà
        $category = $user->categories()
            ->where('name', $name)
            ->where('type', $type)
            ->first();

        if ($category) {
            return $category;
        }

        // Créer la catégorie
        return Category::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'color' => $type === 'income' ? '#10B981' : '#EF4444',
            'icon' => $type === 'income' ? 'coins' : 'shopping-bag',
            'is_active' => true,
        ]);
    }

    /**
     * Calculer la similarité entre deux chaînes
     */
    private function similarity(string $str1, string $str2): float
    {
        similar_text($str1, $str2, $percent);

        return $percent / 100;
    }

    /**
     * Mappings de mots-clés pour catégorisation
     */
    private function getKeywordMappings(): array
    {
        return [
            'alimentation' => [
                'supermarche', 'carrefour', 'auchan',
                'lidl', 'restaurant', 'mcdo', 'food',
                'boulangerie', 'epicerie',
            ],
            'transport' => [
                'essence', 'carburant', 'uber', 'taxi',
                'parking', 'peage', 'sncf', 'ratp',
                'metro', 'bus',
            ],
            'logement' => [
                'loyer', 'rent', 'edf', 'eau', 'gaz',
                'electricite', 'assurance habitation',
            ],
            'sante' => [
                'pharmacie', 'medecin', 'docteur',
                'hopital', 'clinique', 'mutuelle',
            ],
            'loisirs' => [
                'cinema', 'netflix', 'spotify',
                'concert', 'theatre', 'sport',
            ],
            'shopping' => [
                'amazon', 'fnac', 'zara', 'h&m',
                'vetement', 'chaussure',
            ],
            'salaire' => [
                'salaire', 'paie', 'virement',
                'salary', 'traitement',
            ],
            'banque' => [
                'frais bancaire', 'commission',
                'cotisation', 'carte bancaire',
            ],
        ];
    }

    /**
     * Catégoriser en masse les transactions en attente
     */
    public function bulkCategorize(User $user): array
    {
        $pending = $user->transactions()
            ->whereNull('category_id')
            ->get();

        $stats = [
            'total' => $pending->count(),
            'categorized' => 0,
            'failed' => 0,
        ];

        foreach ($pending as $transaction) {
            try {
                if ($this->categorizeBridgeTransaction($transaction)) {
                    $stats['categorized']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                Log::error('Categorization error', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
