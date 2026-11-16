<?php

namespace App\Services;

use RuntimeException;

/** La demande est bien remplie, mais la règle de la course l'interdit. Le message s'affiche tel quel au coureur. */
class InscriptionRefusee extends RuntimeException
{
    public function __construct(string $message, public readonly string $champ = 'epreuve')
    {
        parent::__construct($message);
    }
}
