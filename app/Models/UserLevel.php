<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'level',
        'total_xp',
        'current_level_xp',
        'next_level_xp',
    ];

    protected $casts = [
        'level' => 'integer',
        'total_xp' => 'integer',
        'current_level_xp' => 'integer',
        'next_level_xp' => 'integer',
    ];

    protected $attributes = [
        'level' => 1,
        'total_xp' => 0,
        'current_level_xp' => 0,
        'next_level_xp' => 100,
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculer l'XP requis pour un niveau donné
     *
     * Formule : (niveau - 1)² × 50 + 100
     * Exemple : niveau 5 = 4² × 50 + 100 = 900 XP
     *
     * @param  int  $level  Niveau cible
     * @return int XP requis pour ce niveau
     */
    public static function getXpRequiredForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        // Formule exponentielle : level^2 * 50
        return pow($level - 1, 2) * 50 + 100;
    }

    /**
     * Ajouter de l'XP et calculer les nouveaux niveaux
     *
     * @param  int  $xp  Points d'expérience à ajouter
     * @return array Résultat avec informations de montée de niveau
     */
    public function addXp(int $xp): array
    {
        // Mise à jour des totaux
        $this->total_xp += $xp;
        $this->current_level_xp += $xp;

        $leveledUp = false;
        $levelsGained = 0;

        // Vérifier les montées de niveau
        while ($this->current_level_xp >= $this->next_level_xp) {
            $this->processLevelUp();
            $levelsGained++;
            $leveledUp = true;
        }

        $this->save();

        return $this->buildLevelUpResult($leveledUp, $levelsGained, $xp);
    }

    /**
     * Traiter une montée de niveau
     *
     * Logique séparée pour respecter la limite de 25 lignes
     */
    protected function processLevelUp(): void
    {
        $this->current_level_xp -= $this->next_level_xp;
        $this->level++;

        // Calculer l'XP requis pour le niveau suivant
        $currentLevelXp = self::getXpRequiredForLevel($this->level);
        $nextLevelXp = self::getXpRequiredForLevel($this->level + 1);

        $this->next_level_xp = $nextLevelXp - $currentLevelXp;
    }

    /**
     * Construire le résultat de l'ajout d'XP
     *
     * @param  bool  $leveledUp  A monté de niveau
     * @param  int  $levelsGained  Niveaux gagnés
     * @param  int  $xpAdded  XP ajoutés
     * @return array Résultat formaté
     */
    protected function buildLevelUpResult(bool $leveledUp, int $levelsGained, int $xpAdded): array
    {
        return [
            'leveled_up' => $leveledUp,
            'levels_gained' => $levelsGained,
            'new_level' => $this->level,
            'xp_added' => $xpAdded,
            'total_xp' => $this->total_xp,
            'progress_percentage' => $this->getProgressPercentage(),
        ];
    }

    /**
     * Obtenir le pourcentage de progression vers le niveau suivant
     *
     * @return float Pourcentage (0-100)
     */
    public function getProgressPercentage(): float
    {
        if ($this->next_level_xp <= 0) {
            return 100.0;
        }

        return ($this->current_level_xp / $this->next_level_xp) * 100;
    }

    /**
     * Obtenir le titre basé sur le niveau
     *
     * @return string Titre de l'utilisateur
     */
    public function getTitle(): string
    {
        return match (true) {
            $this->level >= 100 => 'Maître de l\'Épargne',
            $this->level >= 75 => 'Expert Financier',
            $this->level >= 50 => 'Gestionnaire Avancé',
            $this->level >= 25 => 'Économe Expérimenté',
            $this->level >= 10 => 'Apprenti Budgétaire',
            default => 'Débutant'
        };
    }

    /**
     * Obtenir la couleur associée au niveau
     *
     * @return string Couleur hexadécimale
     */
    public function getLevelColor(): string
    {
        return match (true) {
            $this->level >= 100 => '#DC2626', // Rouge légendaire
            $this->level >= 75 => '#7C3AED',  // Violet épique
            $this->level >= 50 => '#059669',  // Vert rare
            $this->level >= 25 => '#3B82F6',  // Bleu uncommon
            default => '#6B7280'              // Gris commun
        };
    }

    /**
     * Vérifier si l'utilisateur peut accéder à une fonctionnalité
     *
     * @param  int  $requiredLevel  Niveau requis
     * @return bool Accès autorisé
     */
    public function canAccess(int $requiredLevel): bool
    {
        return $this->level >= $requiredLevel;
    }

    /**
     * Obtenir les statistiques détaillées du niveau
     *
     * @return array Statistiques complètes
     */
    public function getDetailedStats(): array
    {
        return [
            'current_level' => $this->level,
            'total_xp' => $this->total_xp,
            'current_level_xp' => $this->current_level_xp,
            'next_level_xp' => $this->next_level_xp,
            'progress_percentage' => round($this->getProgressPercentage(), 2),
            'title' => $this->getTitle(),
            'level_color' => $this->getLevelColor(),
            'xp_to_next_level' => $this->next_level_xp - $this->current_level_xp,
        ];
    }
}
