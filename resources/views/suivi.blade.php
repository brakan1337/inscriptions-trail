@extends('layout')
@section('titre', 'Mon inscription')
@section('contenu')
  @php($s = $inscription->statut)
  <h1>{{ $inscription->prenom }}, {{ $inscription->epreuve->nom }}</h1>
  <p class="sous-titre">Référence {{ $inscription->reference }} · gardez cette page, elle permet de suivre votre inscription.</p>

  @error('paiement')<div class="bandeau alerte" role="alert">{{ $message }}</div>@enderror

  <section class="carte">
    <p><span class="statut {{ $s->value }}">{{ $s->libelle() }}</span></p>

    @if ($s === \App\Enums\StatutInscription::Payee)
      <p class="discret">Votre dossard</p>
      <div class="dossard">{{ $inscription->dossard }}</div>
      <p>Départ le {{ $inscription->epreuve->depart_le->translatedFormat('l j F Y à H\hi') }}. Un e-mail de confirmation vous a été envoyé.</p>
      <p class="discret">Justificatif : {{ $inscription->justificatif_type->libelle() }},
        <span class="statut {{ $inscription->justificatif_statut->value }}">{{ $inscription->justificatif_statut->libelle() }}</span>
        @if ($inscription->justificatif_motif) · {{ $inscription->justificatif_motif }} @endif
      </p>
    @elseif ($s->attendPaiement() && $inscription->reservationEnCours())
      <p>Votre place est réservée jusqu'au <strong>{{ $inscription->place_reservee_jusqu_au->translatedFormat('j F à H\hi') }}</strong>.
         Passé ce délai, elle sera proposée à un autre coureur.</p>
      <form method="post" action="{{ route('inscriptions.payer', $inscription) }}">
        @csrf
        <button type="submit">Payer {{ number_format($inscription->montant_centimes / 100, 2, ',', ' ') }} €</button>
      </form>
    @elseif ($s === \App\Enums\StatutInscription::ListeAttente)
      <p>L'épreuve est complète. Vous êtes <strong>{{ $devant + 1 }}<sup>{{ $devant === 0 ? 'er' : 'e' }}</sup></strong> sur la liste d'attente.</p>
      <p class="discret">Dès qu'une place se libère, elle vous est proposée par e-mail, avec {{ \App\Services\Guichet::DELAI_OFFRE_HEURES }} heures pour la payer.</p>
    @elseif ($s === \App\Enums\StatutInscription::Expiree || ($s->attendPaiement() && ! $inscription->reservationEnCours()))
      <p>Le délai de paiement est dépassé et la place a été remise en jeu. Vous pouvez refaire une demande depuis la page de l'épreuve.</p>
      <a class="bouton secondaire" href="{{ route('inscription.formulaire', $inscription->epreuve) }}">Refaire une demande</a>
    @else
      <p>Cette inscription a été annulée par l'organisation.</p>
    @endif
  </section>

  @if ($inscription->ppsAFournir() && in_array($s, [\App\Enums\StatutInscription::Payee, \App\Enums\StatutInscription::EnAttentePaiement, \App\Enums\StatutInscription::OffreEnCours, \App\Enums\StatutInscription::ListeAttente], true))
    @php($fenetre = \App\Services\Guichet::debutFenetrePps($inscription->epreuve))
    <section class="carte">
      <h2>PPS à fournir</h2>
      <p>Faites votre PPS à partir du <strong>{{ $fenetre->format('d/m/Y') }}</strong> : il sera encore valable le jour de la course. Sans lui, pas de dossard au départ.</p>
      @if ($fenetre->isPast())
        <form method="post" action="{{ route('inscriptions.pps', $inscription) }}" class="deux" novalidate>
          @csrf
          <div class="champ">
            <label for="justificatif_numero">Numéro de PPS</label>
            <input id="justificatif_numero" name="justificatif_numero" value="{{ old('justificatif_numero') }}" required>
            @error('justificatif_numero')<p class="erreur">{{ $message }}</p>@enderror
          </div>
          <div class="champ">
            <label for="justificatif_date">Date d'obtention</label>
            <input id="justificatif_date" name="justificatif_date" type="date" value="{{ old('justificatif_date') }}" required>
            @error('justificatif_date')<p class="erreur">{{ $message }}</p>@enderror
          </div>
          <div><button type="submit">Enregistrer mon PPS</button></div>
        </form>
      @endif
    </section>
  @endif
@endsection
