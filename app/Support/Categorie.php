<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Catégorie d'âge pour le classement et l'export du chronométreur.
 *
 * Règle simplifiée : l'âge atteint dans l'année de la course. Le règlement fédéral
 * raisonne par saison ; l'organisateur confirme la grille officielle avant la course.
 */
final class Categorie
{
    /** Âge minimal de chaque catégorie, de la plus âgée à la plus jeune. */
    private const GRILLE = [
        85 => 'M10', 80 => 'M9', 75 => 'M8', 70 => 'M7', 65 => 'M6', 60 => 'M5',
        55 => 'M4', 50 => 'M3', 45 => 'M2', 40 => 'M1', 35 => 'M0',
        23 => 'SE', 20 => 'ES', 18 => 'JU', 16 => 'CA', 0 => 'MI',
    ];

    public static function pour(CarbonInterface $naissance, CarbonInterface $course): string
    {
        $age = $course->year - $naissance->year;
        foreach (self::GRILLE as $minimum => $code) {
            if ($age >= $minimum) {
                return $code;
            }
        }

        return 'MI';
    }
}
