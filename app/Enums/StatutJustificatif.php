<?php

namespace App\Enums;

enum StatutJustificatif: string
{
    case AVerifier = 'a_verifier';
    case Valide = 'valide';
    case Refuse = 'refuse';

    public function libelle(): string
    {
        return match ($this) {
            self::AVerifier => 'À vérifier',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
        };
    }
}
