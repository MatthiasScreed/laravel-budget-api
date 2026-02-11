<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class SimpleCategorizer
{
    /**
     * Règles de correspondance mot-clé → catégorie
     */
    private array $rules = [
        'Alimentation' => ['franprix', 'monoprix', 'carrefour', 'lidl', 'auchan', 'leclerc', 'intermarche', 'casino', 'picard', 'primeur', 'bio c bon', 'naturalia', 'supermarche', 'epicerie', 'marche', 'boucherie', 'boulangerie'],
        'Restaurants' => ['restaurant', 'resto', 'cafe', 'starbucks', 'mcdo', 'mcdonald', 'burger', 'pizza', 'sushi', 'brasserie', 'bistro', 'brazier', 'reve', 'deliveroo', 'uber eats', 'just eat', 'frichti', 'paul', 'pret a manger', 'class croute'],
        'Transports publics' => ['ratp', 'sncf', 'navigo', 'metro', 'bus', 'tram', 'ter', 'tgv', 'ouigo'],
        'Taxi/VTC' => ['uber', 'bolt', 'taxi', 'kapten', 'heetch', 'freenow'],
        'Carburant' => ['total', 'shell', 'bp', 'esso', 'essence', 'carburant', 'station service'],
        'Parking' => ['parking', 'parcmetre', 'stationnement', 'indigo', 'effia', 'q-park'],
        'Streaming' => ['netflix', 'spotify', 'apple.com', 'apple com', 'deezer', 'canal+', 'canal plus', 'disney+', 'disney plus', 'youtube', 'amazon prime', 'prime video'],
        'Téléphone' => ['free mobile', 'orange', 'sfr', 'bouygues tel', 'sosh', 'red by sfr', 'b&you', 'byou'],
        'Internet' => ['free box', 'freebox', 'orange box', 'livebox', 'sfr box', 'bbox'],
        'Électricité' => ['edf', 'engie', 'electricite', 'direct energie', 'totalenergies'],
        'Logement' => ['loyer', 'bailleur', 'syndic', 'charges locatives'],
        'Eau' => ['veolia', 'suez', 'eau de paris', 'facture eau'],
        'Santé' => ['pharmacie', 'medecin', 'docteur', 'hopital', 'clinique', 'laboratoire', 'doctolib'],
        'Mutuelle' => ['mutuelle', 'harmonie', 'malakoff', 'alan', 'axa sante'],
        'Shopping' => ['amazon', 'fnac', 'darty', 'zara', 'h&m', 'uniqlo', 'decathlon', 'ikea', 'sostrene', 'grene', 'action', 'primark', 'galeries lafayette', 'printemps', 'zalando', 'asos', 'shein', 'cdiscount'],
        'Électronique' => ['apple store', 'boulanger', 'ldlc', 'materiel.net'],
        'Vêtements' => ['kiabi', 'celio', 'jules', 'camaieu', 'promod', 'etam', 'pimkie'],
        'Beauté' => ['sephora', 'marionnaud', 'nocibe', 'yves rocher', 'coiffeur', 'salon'],
        'Sport' => ['basic fit', 'fitness', 'gymlib', 'neoness', 'decathlon'],
        'Cinéma' => ['ugc', 'pathe', 'gaumont', 'cinema', 'mk2'],
        'Loisirs' => ['theatre', 'concert', 'musee', 'bowling', 'escape game', 'karting'],
        'Voyages' => ['booking', 'airbnb', 'hotel', 'air france', 'easyjet', 'ryanair', 'vueling', 'transavia', 'expedia', 'kayak'],
        'Banque' => ['frais bancaire', 'commission', 'agios', 'cotisation carte', 'tenue compte'],
        'Services' => ['poste', 'la poste', 'pressing', 'cordonnerie', 'elgi', 'serrurerie'],
        'Assurances' => ['axa', 'maif', 'macif', 'matmut', 'groupama', 'allianz', 'assurance'],
        'Animaux' => ['animalis', 'jardiland', 'truffaut', 'veterinaire', 'animalerie'],
        'Salaire' => ['salaire', 'virement employeur', 'paie', 'remuneration', 'bulletin'],
        'Freelance' => ['facture client', 'honoraires', 'prestation'],
        'Remboursements' => ['remboursement', 'cpam', 'secu', 'avoir', 'retour'],
    ];

    private ?Collection $categories = null;

    /**
     * Catégoriser une transaction
     */
    public function categorize(Transaction $transaction): ?Category
    {
        // Déjà catégorisée
        if ($transaction->category_id) {
            return null;
        }

        $this->loadCategories();
        $description = strtolower($transaction->description);

        // Chercher une correspondance
        foreach ($this->rules as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return $this->categories->get($categoryName);
                }
            }
        }

        // Fallback
        return $this->getDefaultCategory($transaction->type);
    }

    /**
     * Catégoriser et sauvegarder
     */
    public function categorizeAndSave(Transaction $transaction): bool
    {
        $category = $this->categorize($transaction);

        if ($category) {
            $transaction->category_id = $category->id;
            $transaction->save();
            return true;
        }

        return false;
    }

    /**
     * Charger les catégories système
     */
    private function loadCategories(): void
    {
        if ($this->categories === null) {
            $this->categories = Category::whereNull('user_id')
                ->get()
                ->keyBy('name');
        }
    }

    /**
     * Catégorie par défaut selon le type
     */
    private function getDefaultCategory(string $type): ?Category
    {
        $this->loadCategories();

        if ($type === 'expense') {
            return $this->categories->get('Autres dépenses')
                ?? $this->categories->get('Non catégorisé');
        }

        return $this->categories->get('Remboursements')
            ?? $this->categories->firstWhere('type', 'income');
    }
}
