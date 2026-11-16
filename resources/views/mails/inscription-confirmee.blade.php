Bonjour {{ $inscription->prenom }},

Votre inscription au {{ $inscription->epreuve->nom }} est confirmée.

Dossard : {{ $inscription->dossard }}
Départ : {{ $inscription->epreuve->depart_le->translatedFormat('l j F Y à H\hi') }}
Montant réglé : {{ number_format($inscription->montant_centimes / 100, 2, ',', ' ') }} €

@if ($inscription->ppsAFournir())
Il vous reste à fournir votre PPS, à faire à partir du {{ \App\Services\Guichet::debutFenetrePps($inscription->epreuve)->format('d/m/Y') }}, depuis votre page de suivi.
@elseif ($inscription->justificatif_type === \App\Enums\Justificatif::Pps)
Pensez à présenter votre PPS au retrait des dossards.
@else
Pensez à présenter votre licence au retrait des dossards.
@endif

Suivre votre inscription : {{ route('inscriptions.show', $inscription) }}

L'équipe d'organisation
