<?php

namespace App\Services;

use App\Models\FinancialGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProjectService
{
    protected BudgetService $budgetService;

    protected GamingService $gamingService;

    public function __construct(BudgetService $budgetService, GamingService $gamingService)
    {
        $this->budgetService = $budgetService;
        $this->gamingService = $gamingService;
    }

    /**
     * Obtenir tous les templates de projets disponibles
     *
     * @return array Templates disponibles
     */
    public function getAvailableTemplates(): array
    {
        return [
            'travel' => $this->getTravelTemplate(),
            'real_estate' => $this->getRealEstateTemplate(),
            'car' => $this->getCarTemplate(),
            'event' => $this->getEventTemplate(),
            'emergency_fund' => $this->getEmergencyFundTemplate(),
            'investment' => $this->getInvestmentTemplate(),
            'debt_payoff' => $this->getDebtPayoffTemplate(),
            'education' => $this->getEducationTemplate(),
            'home_improvement' => $this->getHomeImprovementTemplate(),
            'business' => $this->getBusinessTemplate(),
        ];
    }

    /**
     * Créer un projet basé sur un template
     *
     * @param  User  $user  Utilisateur propriétaire
     * @param  string  $templateType  Type de template
     * @param  array  $customData  Données personnalisées
     * @return array Projet créé avec objectifs et catégories
     */
    public function createProjectFromTemplate(User $user, string $templateType, array $customData): array
    {
        $template = $this->getTemplate($templateType);
        if (! $template) {
            throw new \InvalidArgumentException("Template {$templateType} non trouvé");
        }

        $project = [
            'goal' => $this->createGoalFromTemplate($user, $template, $customData),
            'categories' => $this->createCategoriesFromTemplate($user, $template),
            'milestones' => $this->generateMilestones($template, $customData),
            'suggestions' => $this->generateProjectSuggestions($template, $customData),
        ];

        // XP pour création de projet
        $this->gamingService->addExperience($user, 50, 'project_created');

        return $project;
    }

    /**
     * Template pour voyage
     *
     * @return array Template de voyage
     */
    protected function getTravelTemplate(): array
    {
        return [
            'name' => 'Voyage',
            'description' => 'Planifier et budgétiser un voyage',
            'icon' => 'airplane',
            'color' => '#10B981',
            'type' => 'purchase',
            'categories' => [
                ['name' => 'Transport', 'percentage' => 40, 'icon' => 'car'],
                ['name' => 'Hébergement', 'percentage' => 30, 'icon' => 'home'],
                ['name' => 'Nourriture', 'percentage' => 15, 'icon' => 'utensils'],
                ['name' => 'Activités', 'percentage' => 10, 'icon' => 'ticket'],
                ['name' => 'Divers', 'percentage' => 5, 'icon' => 'shopping-bag'],
            ],
            'default_duration_months' => 12,
            'tips' => [
                'Réservez vos billets d\'avion 2-3 mois à l\'avance',
                'Recherchez des hébergements avec annulation gratuite',
                'Prévoyez 20% de budget supplémentaire pour les imprévus',
            ],
        ];
    }

    /**
     * Template pour immobilier
     *
     * @return array Template immobilier
     */
    protected function getRealEstateTemplate(): array
    {
        return [
            'name' => 'Achat Immobilier',
            'description' => 'Épargner pour un achat immobilier',
            'icon' => 'home',
            'color' => '#3B82F6',
            'type' => 'investment',
            'categories' => [
                ['name' => 'Apport personnel', 'percentage' => 70, 'icon' => 'piggy-bank'],
                ['name' => 'Frais de notaire', 'percentage' => 8, 'icon' => 'file-text'],
                ['name' => 'Frais d\'agence', 'percentage' => 5, 'icon' => 'users'],
                ['name' => 'Travaux', 'percentage' => 12, 'icon' => 'hammer'],
                ['name' => 'Déménagement', 'percentage' => 3, 'icon' => 'truck'],
                ['name' => 'Garanties', 'percentage' => 2, 'icon' => 'shield'],
            ],
            'default_duration_months' => 36,
            'tips' => [
                'Visez un apport de 10-20% du prix d\'achat',
                'Prévoyez 7-8% de frais de notaire',
                'Négociez les frais d\'agence',
            ],
        ];
    }

    /**
     * Template pour voiture
     *
     * @return array Template voiture
     */
    protected function getCarTemplate(): array
    {
        return [
            'name' => 'Achat Voiture',
            'description' => 'Épargner pour l\'achat d\'une voiture',
            'icon' => 'car',
            'color' => '#EF4444',
            'type' => 'purchase',
            'categories' => [
                ['name' => 'Prix d\'achat', 'percentage' => 80, 'icon' => 'tag'],
                ['name' => 'Assurance', 'percentage' => 8, 'icon' => 'shield'],
                ['name' => 'Carte grise', 'percentage' => 2, 'icon' => 'id-card'],
                ['name' => 'Contrôle technique', 'percentage' => 2, 'icon' => 'search'],
                ['name' => 'Équipements', 'percentage' => 5, 'icon' => 'cog'],
                ['name' => 'Divers', 'percentage' => 3, 'icon' => 'more-horizontal'],
            ],
            'default_duration_months' => 18,
            'tips' => [
                'Comparez les assurances avant l\'achat',
                'Vérifiez l\'historique du véhicule',
                'Négociez le prix d\'achat',
            ],
        ];
    }

    /**
     * Template pour événement
     *
     * @return array Template événement
     */
    protected function getEventTemplate(): array
    {
        return [
            'name' => 'Événement Spécial',
            'description' => 'Organiser un événement (mariage, anniversaire, etc.)',
            'icon' => 'gift',
            'color' => '#F59E0B',
            'type' => 'purchase',
            'categories' => [
                ['name' => 'Lieu', 'percentage' => 35, 'icon' => 'map-pin'],
                ['name' => 'Traiteur', 'percentage' => 25, 'icon' => 'utensils'],
                ['name' => 'Décoration', 'percentage' => 15, 'icon' => 'star'],
                ['name' => 'Animation', 'percentage' => 10, 'icon' => 'music'],
                ['name' => 'Tenue', 'percentage' => 8, 'icon' => 'shirt'],
                ['name' => 'Divers', 'percentage' => 7, 'icon' => 'more-horizontal'],
            ],
            'default_duration_months' => 12,
            'tips' => [
                'Réservez le lieu 6-12 mois à l\'avance',
                'Demandez plusieurs devis pour chaque prestation',
                'Prévoyez 15% de budget supplémentaire',
            ],
        ];
    }

    /**
     * Template pour fonds d'urgence
     *
     * @return array Template fonds d'urgence
     */
    protected function getEmergencyFundTemplate(): array
    {
        return [
            'name' => 'Fonds d\'Urgence',
            'description' => 'Constituer une réserve de sécurité',
            'icon' => 'shield',
            'color' => '#8B5CF6',
            'type' => 'emergency_fund',
            'categories' => [
                ['name' => 'Épargne de précaution', 'percentage' => 100, 'icon' => 'shield'],
            ],
            'default_duration_months' => 24,
            'tips' => [
                'Visez 3-6 mois de charges courantes',
                'Placez sur un livret accessible rapidement',
                'Alimentez régulièrement, même petit montant',
            ],
        ];
    }

    /**
     * Template pour remboursement de dette
     *
     * @return array Template dette
     */
    protected function getDebtPayoffTemplate(): array
    {
        return [
            'name' => 'Remboursement de Dette',
            'description' => 'Plan de remboursement accéléré',
            'icon' => 'credit-card',
            'color' => '#DC2626',
            'type' => 'debt_payoff',
            'categories' => [
                ['name' => 'Remboursement principal', 'percentage' => 100, 'icon' => 'minus-circle'],
            ],
            'default_duration_months' => 24,
            'tips' => [
                'Priorisez les dettes à taux élevé',
                'Négociez avec vos créanciers',
                'Évitez de nouvelles dettes',
            ],
        ];
    }

    /**
     * Template pour éducation
     *
     * @return array Template éducation
     */
    protected function getEducationTemplate(): array
    {
        return [
            'name' => 'Formation/Éducation',
            'description' => 'Financer une formation ou des études',
            'icon' => 'graduation-cap',
            'color' => '#7C3AED',
            'type' => 'investment',
            'categories' => [
                ['name' => 'Frais de scolarité', 'percentage' => 60, 'icon' => 'book'],
                ['name' => 'Matériel', 'percentage' => 15, 'icon' => 'laptop'],
                ['name' => 'Logement', 'percentage' => 20, 'icon' => 'home'],
                ['name' => 'Transport', 'percentage' => 5, 'icon' => 'car'],
            ],
            'default_duration_months' => 18,
            'tips' => [
                'Recherchez les aides disponibles',
                'Comparez les différents établissements',
                'Planifiez tôt pour éviter le stress financier',
            ],
        ];
    }

    /**
     * Template pour amélioration maison
     *
     * @return array Template travaux
     */
    protected function getHomeImprovementTemplate(): array
    {
        return [
            'name' => 'Travaux Maison',
            'description' => 'Rénover ou améliorer son logement',
            'icon' => 'hammer',
            'color' => '#F97316',
            'type' => 'purchase',
            'categories' => [
                ['name' => 'Matériaux', 'percentage' => 50, 'icon' => 'package'],
                ['name' => 'Main d\'œuvre', 'percentage' => 30, 'icon' => 'users'],
                ['name' => 'Équipements', 'percentage' => 10, 'icon' => 'tool'],
                ['name' => 'Démarches', 'percentage' => 5, 'icon' => 'file-text'],
                ['name' => 'Imprévu', 'percentage' => 5, 'icon' => 'alert-triangle'],
            ],
            'default_duration_months' => 15,
            'tips' => [
                'Demandez 3 devis minimum',
                'Prévoyez 15-20% de budget supplémentaire',
                'Vérifiez les assurances des artisans',
            ],
        ];
    }

    /**
     * Template pour création d'entreprise
     *
     * @return array Template business
     */
    protected function getBusinessTemplate(): array
    {
        return [
            'name' => 'Création d\'Entreprise',
            'description' => 'Capital de démarrage pour une entreprise',
            'icon' => 'briefcase',
            'color' => '#0891B2',
            'type' => 'investment',
            'categories' => [
                ['name' => 'Capital initial', 'percentage' => 40, 'icon' => 'dollar-sign'],
                ['name' => 'Équipements', 'percentage' => 25, 'icon' => 'laptop'],
                ['name' => 'Stock initial', 'percentage' => 15, 'icon' => 'package'],
                ['name' => 'Marketing', 'percentage' => 10, 'icon' => 'megaphone'],
                ['name' => 'Juridique', 'percentage' => 5, 'icon' => 'scale'],
                ['name' => 'Divers', 'percentage' => 5, 'icon' => 'more-horizontal'],
            ],
            'default_duration_months' => 18,
            'tips' => [
                'Réalisez une étude de marché',
                'Consultez un expert-comptable',
                'Prévoyez de la trésorerie pour 6 mois',
            ],
        ];
    }

    /**
     * Obtenir un template spécifique
     *
     * @param  string  $type  Type de template
     * @return array|null Template ou null
     */
    protected function getTemplate(string $type): ?array
    {
        $templates = $this->getAvailableTemplates();

        return $templates[$type] ?? null;
    }

    /**
     * Créer un objectif basé sur un template
     *
     * @param  User  $user  Utilisateur concerné
     * @param  array  $template  Template à utiliser
     * @param  array  $customData  Données personnalisées
     * @return FinancialGoal Objectif créé
     */
    protected function createGoalFromTemplate(User $user, array $template, array $customData): FinancialGoal
    {
        $targetAmount = $customData['target_amount'] ?? 5000;
        $targetDate = isset($customData['target_date']) ?
            Carbon::parse($customData['target_date']) :
            now()->addMonths($template['default_duration_months']);

        return $user->financialGoals()->create([
            'name' => $customData['name'] ?? $template['name'],
            'description' => $customData['description'] ?? $template['description'],
            'target_amount' => $targetAmount,
            'target_date' => $targetDate,
            'type' => $template['type'],
            'color' => $template['color'],
            'icon' => $template['icon'],
            'monthly_target' => $this->calculateMonthlyTarget($targetAmount, $targetDate),
            'tags' => ['template:'.array_search($template, $this->getAvailableTemplates())],
        ]);
    }

    /**
     * Créer les catégories basées sur un template
     *
     * @param  User  $user  Utilisateur concerné
     * @param  array  $template  Template à utiliser
     * @return Collection Catégories créées
     */
    protected function createCategoriesFromTemplate(User $user, array $template): Collection
    {
        $categories = collect();

        foreach ($template['categories'] as $categoryData) {
            $category = $user->categories()->create([
                'name' => $categoryData['name'],
                'type' => 'expense',
                'color' => $this->generateCategoryColor(),
                'icon' => $categoryData['icon'],
                'sort_order' => count($categories),
            ]);

            $categories->push($category);
        }

        return $categories;
    }

    /**
     * Calculer l'objectif mensuel
     *
     * @param  float  $targetAmount  Montant cible
     * @param  Carbon  $targetDate  Date cible
     * @return float Objectif mensuel
     */
    protected function calculateMonthlyTarget(float $targetAmount, Carbon $targetDate): float
    {
        $monthsRemaining = max(1, now()->diffInMonths($targetDate));

        return $targetAmount / $monthsRemaining;
    }

    /**
     * Générer une couleur pour une catégorie
     *
     * @return string Couleur hexadécimale
     */
    protected function generateCategoryColor(): string
    {
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * Générer les étapes intermédiaires
     *
     * @param  array  $template  Template utilisé
     * @param  array  $customData  Données personnalisées
     * @return array Étapes générées
     */
    protected function generateMilestones(array $template, array $customData): array
    {
        $targetAmount = $customData['target_amount'] ?? 5000;
        $percentages = [25, 50, 75, 100];
        $milestones = [];

        foreach ($percentages as $percentage) {
            $milestones[] = [
                'percentage' => $percentage,
                'amount' => ($percentage / 100) * $targetAmount,
                'description' => $this->getMilestoneDescription($percentage, $template['name']),
            ];
        }

        return $milestones;
    }

    /**
     * Obtenir la description d'une étape
     *
     * @param  int  $percentage  Pourcentage de l'étape
     * @param  string  $projectName  Nom du projet
     * @return string Description
     */
    protected function getMilestoneDescription(int $percentage, string $projectName): string
    {
        return match ($percentage) {
            25 => "Premier quart de votre {$projectName} atteint !",
            50 => "Bravo ! Vous êtes à mi-chemin de votre {$projectName}",
            75 => "Plus que 25% ! Votre {$projectName} approche",
            100 => "Félicitations ! Votre {$projectName} est financé !",
            default => "{$percentage}% de votre {$projectName} atteint"
        };
    }

    /**
     * Générer des suggestions pour le projet
     *
     * @param  array  $template  Template utilisé
     * @param  array  $customData  Données personnalisées
     * @return array Suggestions
     */
    protected function generateProjectSuggestions(array $template, array $customData): array
    {
        $suggestions = $template['tips'] ?? [];

        // Ajouter des suggestions génériques
        $suggestions[] = "Automatisez vos virements d'épargne";
        $suggestions[] = 'Révisez votre budget mensuel';

        return $suggestions;
    }

    /**
     * Obtenir les projets populaires
     *
     * @return array Projets populaires avec statistiques
     */
    public function getPopularProjects(): array
    {
        return Cache::remember('popular_projects', 3600, function () {
            $templates = $this->getAvailableTemplates();

            // Simuler la popularité (à remplacer par de vraies stats)
            $popularity = [
                'travel' => 85,
                'emergency_fund' => 78,
                'car' => 72,
                'real_estate' => 65,
                'event' => 58,
                'home_improvement' => 45,
                'education' => 42,
                'investment' => 38,
                'debt_payoff' => 35,
                'business' => 28,
            ];

            return collect($templates)
                ->map(function ($template, $key) use ($popularity) {
                    return array_merge($template, [
                        'key' => $key,
                        'popularity_score' => $popularity[$key] ?? 0,
                    ]);
                })
                ->sortByDesc('popularity_score')
                ->values()
                ->toArray();
        });
    }
}
