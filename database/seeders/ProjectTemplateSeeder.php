<?php

namespace Database\Seeders;

use App\Models\ProjectTemplate;
use Illuminate\Database\Seeder;

class ProjectTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'travel',
                'name' => 'Voyage',
                'description' => 'Planifier et budgétiser un voyage (vacances, tour du monde, city trip)',
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
                    'Comparez les assurances voyage',
                ],
                'milestones' => [
                    ['percentage' => 25, 'description' => 'Premier quart atteint'],
                    ['percentage' => 50, 'description' => 'Mi-parcours !'],
                    ['percentage' => 75, 'description' => 'Presque là !'],
                    ['percentage' => 100, 'description' => 'Objectif atteint !'],
                ],
                'min_amount' => 500,
                'max_amount' => 50000,
                'popularity_score' => 85,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'emergency_fund',
                'name' => 'Fonds d\'Urgence',
                'description' => 'Constituer une réserve de sécurité pour faire face aux imprévus',
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
                    'Ne touchez qu\'en cas de vraie urgence',
                ],
                'milestones' => [
                    ['percentage' => 25, 'description' => '1 mois de charges couvertes'],
                    ['percentage' => 50, 'description' => '3 mois de sécurité'],
                    ['percentage' => 75, 'description' => '4-5 mois de tranquillité'],
                    ['percentage' => 100, 'description' => 'Pleinement protégé !'],
                ],
                'min_amount' => 1000,
                'max_amount' => 30000,
                'popularity_score' => 78,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'car',
                'name' => 'Achat Voiture',
                'description' => 'Épargner pour l\'achat d\'une voiture neuve ou d\'occasion',
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
                    'Prévoyez un budget d\'entretien annuel',
                ],
                'milestones' => null,
                'min_amount' => 2000,
                'max_amount' => 80000,
                'popularity_score' => 72,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'real_estate',
                'name' => 'Achat Immobilier',
                'description' => 'Épargner pour un achat immobilier (apport, frais, travaux)',
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
                    'Anticipez les travaux éventuels',
                ],
                'milestones' => null,
                'min_amount' => 10000,
                'max_amount' => 200000,
                'popularity_score' => 65,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'event',
                'name' => 'Événement Spécial',
                'description' => 'Organiser un événement (mariage, anniversaire, fête)',
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
                    'Créez une liste de priorités',
                ],
                'milestones' => null,
                'min_amount' => 1000,
                'max_amount' => 50000,
                'popularity_score' => 58,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'education',
                'name' => 'Formation/Éducation',
                'description' => 'Financer une formation, des études ou un diplôme',
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
                    'Regardez les possibilités d\'alternance',
                ],
                'milestones' => null,
                'min_amount' => 500,
                'max_amount' => 50000,
                'popularity_score' => 42,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'home_improvement',
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
                    'Regardez les aides de l\'État',
                ],
                'milestones' => null,
                'min_amount' => 1000,
                'max_amount' => 100000,
                'popularity_score' => 45,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'business',
                'name' => 'Création d\'Entreprise',
                'description' => 'Capital de démarrage pour créer son entreprise',
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
                    'Renseignez-vous sur les aides à la création',
                ],
                'milestones' => null,
                'min_amount' => 5000,
                'max_amount' => 100000,
                'popularity_score' => 28,
                'is_active' => true,
                'is_premium' => true,
            ],

            [
                'key' => 'debt_payoff',
                'name' => 'Remboursement de Dette',
                'description' => 'Plan de remboursement accéléré de vos dettes',
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
                    'Consultez un conseiller en gestion de dettes si nécessaire',
                ],
                'milestones' => [
                    ['percentage' => 25, 'description' => 'Un quart remboursé !'],
                    ['percentage' => 50, 'description' => 'Mi-parcours, tenez bon !'],
                    ['percentage' => 75, 'description' => 'Presque libéré !'],
                    ['percentage' => 100, 'description' => 'Dette remboursée, félicitations !'],
                ],
                'min_amount' => 500,
                'max_amount' => 100000,
                'popularity_score' => 35,
                'is_active' => true,
                'is_premium' => false,
            ],

            [
                'key' => 'investment',
                'name' => 'Investissement',
                'description' => 'Capital pour investir (bourse, crypto, immobilier locatif)',
                'icon' => 'trending-up',
                'color' => '#059669',
                'type' => 'investment',
                'categories' => [
                    ['name' => 'Capital d\'investissement', 'percentage' => 100, 'icon' => 'dollar-sign'],
                ],
                'default_duration_months' => 24,
                'tips' => [
                    'Diversifiez vos investissements',
                    'Informez-vous sur les risques',
                    'Consultez un conseiller financier',
                    'N\'investissez que ce que vous pouvez perdre',
                ],
                'milestones' => null,
                'min_amount' => 1000,
                'max_amount' => 500000,
                'popularity_score' => 38,
                'is_active' => true,
                'is_premium' => true,
            ],
        ];

        foreach ($templates as $template) {
            ProjectTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }

        $this->command->info('✅ '.count($templates).' templates de projets créés avec succès !');
    }
}
