Bonjour {{ $inscription->prenom }},

Une place s'est libérée sur le {{ $inscription->epreuve->nom }}, et c'est votre tour sur la liste d'attente.

Elle vous est réservée jusqu'au {{ $inscription->place_reservee_jusqu_au->translatedFormat('l j F à H\hi') }}.
Passé ce délai, elle sera proposée au coureur suivant.

Payer et confirmer : {{ route('inscriptions.show', $inscription) }}

L'équipe d'organisation
