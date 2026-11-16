<?php

namespace App\Enums;

/**
 * Pour courir, il faut une licence d'athlétisme ou un PPS (Parcours Prévention Santé),
 * l'attestation en ligne qui remplace le certificat médical pour les non-licenciés.
 * Le PPS n'est valable que trois mois : il doit l'être encore le jour de la course.
 */
enum Justificatif: string
{
    case Licence = 'licence';
    case Pps = 'pps';

    public const VALIDITE_PPS_MOIS = 3;

    public function libelle(): string
    {
        return match ($this) {
            self::Licence => 'Licence d\'athlétisme',
            self::Pps => 'PPS (Parcours Prévention Santé)',
        };
    }
}
