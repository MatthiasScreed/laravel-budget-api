<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\UserCategorizationPattern;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service de catégorisation intelligente des transactions
 *
 * Architecture hybride en 4 couches :
 * 1. Patterns utilisateur (apprentissage des corrections)
 * 2. Patterns par mots-clés (règles déterministes)
 * 3. Historique utilisateur (transactions similaires)
 * 4. Montants typiques (fallback)
 */
class TransactionCategorizationService
{
    /**
     * Patterns de mots-clés par catégorie
     */
    protected array $patterns = [
        'alimentation' => [
            'carrefour', 'lidl', 'auchan', 'leclerc', 'casino', 'franprix',
            'monoprix', 'intermarche', 'super u', 'cora', 'geant',
            'supermarche', 'courses', 'epicerie', 'marche',
            'restaurant', 'resto', 'pizza', 'burger', 'mcdo', 'kfc',
            'subway', 'boulangerie', 'patisserie', 'boucherie',
            'poissonnerie', 'fromagerie', 'traiteur', 'kebab',
            'sushi', 'indien', 'chinois', 'thai', 'italien',
            'bistrot', 'brasserie', 'cafe', 'bar', 'bistro',
        ],
        'transport' => [
            'sncf', 'ratp', 'uber', 'taxi', 'essence', 'carburant',
            'peage', 'parking', 'station service', 'total', 'shell',
            'esso', 'bp', 'autoroute', 'metro', 'bus', 'tramway',
            'train', 'tgv', 'ouigo', 'blablacar', 'flixbus',
            'velib', 'lime', 'trottinette', 'scooter', 'garage',
            'reparation auto', 'controle technique', 'lavage auto',
            'air france', 'easyjet', 'ryanair', 'vol', 'avion',
        ],
        'logement' => [
            'loyer', 'charges', 'electricite', 'edf', 'engie', 'gaz',
            'eau', 'veolia', 'suez', 'chauffage', 'copropriete',
            'syndic', 'assurance habitation', 'taxe habitation',
            'taxe fonciere', 'agence immobiliere', 'immobilier',
            'hypotheque', 'pret immobilier', 'credit immobilier',
        ],
        'abonnements' => [
            'netflix', 'spotify', 'disney', 'prime video', 'amazon prime',
            'deezer', 'apple music', 'youtube premium', 'twitch',
            'orange', 'sfr', 'bouygues', 'free', 'sosh', 'red',
            'internet', 'telephone', 'mobile', 'forfait', 'box',
            'canal+', 'beinsports', 'salles de sport', 'fitness',
            'basic fit', 'keep cool', 'gymlib', 'abonnement',
        ],
        'sante' => [
            'pharmacie', 'medecin', 'docteur', 'dentiste', 'ophtalmo',
            'kine', 'osteopathe', 'psychologue', 'hopital',
            'clinique', 'laboratoire', 'analyses', 'ordonnance',
            'medicaments', 'lunettes', 'optique', 'audioprothese',
            'mutuelle', 'assurance sante', 'cpam', 'securite sociale',
        ],
        'loisirs' => [
            'steam', 'playstation', 'xbox', 'nintendo', 'jeux video',
            'cinema', 'ugc', 'gaumont', 'pathe', 'mk2', 'theatre',
            'concert', 'spectacle', 'fnac', 'cultura', 'micromania',
            'livres', 'librairie', 'bd', 'manga', 'comics',
            'parc attraction', 'disneyland', 'asterix', 'futuroscope',
            'zoo', 'aquarium', 'musee', 'exposition', 'bowling',
            'laser game', 'escape game', 'karting', 'paintball',
        ],
        'vetements' => [
            'zara', 'h&m', 'uniqlo', 'kiabi', 'c&a', 'primark',
            'pull&bear', 'bershka', 'mango', 'celio', 'jules',
            'nike', 'adidas', 'decathlon', 'intersport', 'go sport',
            'chaussures', 'vetements', 'mode', 'boutique',
            'galeries lafayette', 'printemps', 'sephora',
            'nocibe', 'marionnaud', 'parfumerie', 'coiffeur',
            'salon de coiffure', 'barbier', 'estheticienne',
        ],
        'education' => [
            'ecole', 'universite', 'campus', 'scolarite', 'inscription',
            'cantine', 'fournitures scolaires', 'livres scolaires',
            'cours particuliers', 'soutien scolaire', 'acadomia',
            'completude', 'formation', 'udemy', 'coursera',
            'openclassrooms', 'le wagon', 'creche', 'nounou',
            'baby sitting', 'centre aere', 'colonie',
        ],
        'services_financiers' => [
            'banque', 'frais bancaires', 'commission', 'cotisation',
            'carte bancaire', 'assurance', 'pret', 'credit',
            'interets', 'decouvert', 'virement', 'transfert',
            'paypal', 'revolut', 'n26', 'boursorama', 'fortuneo',
        ],
        'shopping' => [
            'amazon', 'ebay', 'cdiscount', 'rue du commerce',
            'rakuten', 'aliexpress', 'wish', 'shein', 'asos',
            'ikea', 'conforama', 'but', 'maison du monde',
            'leroy merlin', 'castorama', 'bricolage', 'jardinage',
            'action', 'gifi', 'bazar', 'hema', 'normal',
        ],
        'professionnel' => [
            'urssaf', 'rsi', 'impots', 'taxes', 'comptable',
            'expert comptable', 'avocat', 'notaire', 'huissier',
            'assurance pro', 'mutuelle pro', 'cotisation pro',
            'chambre commerce', 'cci', 'pole emploi',
        ],
        'animaux' => [
            'veterinaire', 'veto', 'animaux', 'croquettes',
            'animalerie', 'jardiland', 'botanic', 'maxi zoo',
            'tom&co', 'pension animaux', 'toilettage', 'chat',
            'chien', 'vaccin animal',
        ],
        'cadeaux' => [
            'cadeau', 'anniversaire', 'noel', 'fete', 'don',
            'association', 'charite', 'ong', 'croix rouge',
            'restos du coeur', 'unicef', 'wwf', 'greenpeace',
        ],
    ];

    /**
     * Map patterns vers catégories
     */
    protected array $categoryMapping = [
        'alimentation' => ['Alimentation', 'Courses', 'Restaurants'],
        'transport' => ['Transport', 'Carburant', 'Transports'],
        'logement' => ['Logement', 'Charges', 'Loyer'],
        'abonnements' => ['Abonnements', 'Telecom', 'Internet'],
        'sante' => ['Sante', 'Medical', 'Pharmacie'],
        'loisirs' => ['Loisirs', 'Divertissement', 'Sport'],
        'vetements' => ['Vetements', 'Mode', 'Beaute'],
        'education' => ['Education', 'Formation', 'Enfants'],
        'services_financiers' => ['Services financiers', 'Banque'],
        'shopping' => ['Shopping', 'Achats', 'Divers'],
        'professionnel' => ['Professionnel', 'Impots', 'Taxes'],
        'animaux' => ['Animaux', 'Veterinaire'],
        'cadeaux' => ['Cadeaux', 'Dons'],
    ];

    /**
     * Catégoriser une transaction (méthode principale)
     */
    public function categorize(Transaction $transaction): ?Category
    {
        if ($transaction->category_id) {
            return $transaction->category;
        }

        $suggestions = $this->getAllSuggestions($transaction);

        if (empty($suggestions)) {
            return null;
        }

        $best = $suggestions[0];

        // Appliquer uniquement si confiance >= 70%
        if ($best['score'] >= 0.70) {
            return $best['category'];
        }

        return null;
    }

    /**
     * Obtenir toutes les suggestions avec scoring
     */
    protected function getAllSuggestions(Transaction $tx): array
    {
        $suggestions = [];

        // Couche 1: Pattern utilisateur (priorité max)
        $userPattern = $this->matchByUserPatterns($tx);
        if ($userPattern) {
            $suggestions[] = [
                'category' => $userPattern,
                'score' => 0.95,
                'method' => 'user_pattern',
            ];

            return $suggestions; // Retour immédiat
        }

        // Couche 2: Keywords
        $keywordMatch = $this->matchByKeywords($tx);
        if ($keywordMatch) {
            $suggestions[] = [
                'category' => $keywordMatch,
                'score' => 0.80,
                'method' => 'keywords',
            ];
        }

        // Couche 3: Historique
        $historyMatch = $this->matchByHistory($tx);
        if ($historyMatch && $historyMatch->id !== $keywordMatch?->id) {
            $suggestions[] = [
                'category' => $historyMatch,
                'score' => 0.60,
                'method' => 'history',
            ];
        }

        // Couche 4: Montant
        $amountMatch = $this->matchByAmount($tx);
        if ($amountMatch &&
            $amountMatch->id !== $keywordMatch?->id &&
            $amountMatch->id !== $historyMatch?->id
        ) {
            $suggestions[] = [
                'category' => $amountMatch,
                'score' => 0.40,
                'method' => 'amount',
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    /**
     * Matcher par patterns utilisateur
     */
    protected function matchByUserPatterns(Transaction $tx): ?Category
    {
        $normalizer = app(TransactionNormalizerService::class);
        $merchant = $normalizer->extractMerchant($tx->description);

        if (! $merchant) {
            return null;
        }

        $patterns = $this->getCachedUserPatterns($tx->user_id);

        $match = $patterns->first(function ($pattern) use ($merchant) {
            return str_contains($merchant, $pattern->pattern);
        });

        return $match?->category;
    }

    /**
     * Récupérer patterns utilisateur (avec cache)
     */
    protected function getCachedUserPatterns(int $userId)
    {
        $cacheKey = "user_patterns:{$userId}";

        return Cache::tags(["user:{$userId}", 'patterns'])
            ->remember($cacheKey, 3600, function () use ($userId) {
                return UserCategorizationPattern::where('user_id', $userId)
                    ->with('category')
                    ->orderBy('confidence', 'desc')
                    ->get();
            });
    }

    /**
     * Matcher par mots-clés (avec cache)
     */
    protected function matchByKeywords(Transaction $tx): ?Category
    {
        $normalizer = app(TransactionNormalizerService::class);
        $normalized = $normalizer->normalize($tx->description);
        $text = strtolower($normalized.' '.($tx->label ?? ''));

        $patterns = $this->getCachedPatterns();
        $categoryMapping = $this->getCachedCategoryMapping();

        $matchedPattern = null;
        $maxMatches = 0;

        foreach ($patterns as $patternName => $keywords) {
            $matches = $this->countKeywordMatches($text, $keywords);

            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $matchedPattern = $patternName;
            }
        }

        if ($matchedPattern && $maxMatches > 0) {
            return $this->findCategoryByPattern(
                $matchedPattern,
                $tx->type
            );
        }

        return null;
    }

    /**
     * Compter matches de keywords
     */
    protected function countKeywordMatches(string $text, array $keywords): int
    {
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $matches++;
            }
        }

        return $matches;
    }

    /**
     * Cache des patterns
     */
    protected function getCachedPatterns(): array
    {
        return Cache::tags(['categorization', 'patterns'])
            ->remember('categorization_patterns', 86400, function () {
                return $this->patterns;
            });
    }

    /**
     * Cache du mapping
     */
    protected function getCachedCategoryMapping(): array
    {
        return Cache::tags(['categorization', 'mapping'])
            ->remember('category_mapping', 86400, function () {
                return $this->categoryMapping;
            });
    }

    /**
     * Matcher par historique utilisateur
     */
    protected function matchByHistory(Transaction $tx): ?Category
    {
        $words = $this->extractWords($tx->description);

        if (empty($words)) {
            return null;
        }

        $similar = Transaction::where('user_id', $tx->user_id)
            ->where('type', $tx->type)
            ->whereNotNull('category_id')
            ->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->orWhere('description', 'LIKE', "%{$word}%");
                }
            })
            ->with('category')
            ->latest()
            ->first();

        return $similar?->category;
    }

    /**
     * Extraire mots significatifs (>3 lettres)
     */
    protected function extractWords(string $description): array
    {
        $words = explode(' ', $description);

        return array_filter($words, fn ($w) => strlen($w) > 3);
    }

    /**
     * Matcher par montant typique
     */
    protected function matchByAmount(Transaction $tx): ?Category
    {
        $amount = abs($tx->amount);

        $ranges = [
            'abonnements' => [5, 50],
            'transport' => [1.9, 15],
            'sante' => [20, 100],
        ];

        foreach ($ranges as $pattern => [$min, $max]) {
            if ($amount >= $min && $amount <= $max) {
                $category = $this->findCategoryByPattern($pattern, $tx->type);

                if ($category) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Trouver catégorie par pattern (avec cache)
     */
    protected function findCategoryByPattern(
        string $pattern,
        string $type
    ): ?Category {
        $cacheKey = "category:pattern:{$pattern}:type:{$type}";

        return Cache::tags(['categories'])
            ->remember($cacheKey, 3600, function () use ($pattern, $type) {
                $categoryNames = $this->categoryMapping[$pattern] ?? [];

                foreach ($categoryNames as $name) {
                    $category = Category::where('name', $name)
                        ->where('type', $type)
                        ->first();

                    if ($category) {
                        return $category;
                    }
                }

                return null;
            });
    }

    /**
     * Apprendre d'une correction utilisateur
     */
    public function learnFromCorrection(Transaction $tx): void
    {
        $normalizer = app(TransactionNormalizerService::class);
        $pattern = $normalizer->extractMerchant($tx->description);

        if (! $pattern || strlen($pattern) < 3) {
            return;
        }

        UserCategorizationPattern::updateOrCreate(
            [
                'user_id' => $tx->user_id,
                'pattern' => $pattern,
                'category_id' => $tx->category_id,
            ],
            [
                'match_count' => DB::raw('match_count + 1'),
                'confidence' => DB::raw('LEAST(confidence + 0.05, 0.95)'),
            ]
        );

        $this->invalidateUserCache($tx->user_id);
    }

    /**
     * Invalider cache utilisateur
     */
    protected function invalidateUserCache(int $userId): void
    {
        Cache::tags(["user:{$userId}", 'patterns'])->flush();
    }

    /**
     * Obtenir suggestions avec détails
     */
    public function getSuggestions(Transaction $tx): array
    {
        $suggestions = $this->getAllSuggestions($tx);

        return array_map(function ($suggestion) {
            return [
                'category' => $suggestion['category'],
                'confidence' => $suggestion['score'],
                'reason' => $this->getReasonLabel($suggestion['method']),
            ];
        }, array_slice($suggestions, 0, 3));
    }

    /**
     * Label de la raison
     */
    protected function getReasonLabel(string $method): string
    {
        return match ($method) {
            'user_pattern' => 'Vos habitudes',
            'keywords' => 'Correspondance mots-clés',
            'history' => 'Historique similaire',
            'amount' => 'Montant typique',
            default => 'Algorithme'
        };
    }

    /**
     * Analyser qualité de catégorisation
     */
    public function analyzeQuality(int $userId): array
    {
        $total = Transaction::where('user_id', $userId)->count();
        $categorized = Transaction::where('user_id', $userId)
            ->whereNotNull('category_id')
            ->count();

        $percentage = $total > 0
            ? round(($categorized / $total) * 100, 2)
            : 0;

        return [
            'total_transactions' => $total,
            'categorized' => $categorized,
            'uncategorized' => $total - $categorized,
            'percentage' => $percentage,
            'quality_score' => $this->calculateQualityScore($percentage),
        ];
    }

    /**
     * Score de qualité textuel
     */
    protected function calculateQualityScore(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'Excellent';
        }
        if ($percentage >= 70) {
            return 'Bon';
        }
        if ($percentage >= 50) {
            return 'Moyen';
        }

        return 'Faible';
    }
}
