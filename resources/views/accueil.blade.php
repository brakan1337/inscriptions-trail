@extends('layout')
@section('titre', 'Les épreuves')
@section('contenu')
  <h1>Trail des crêtes</h1>
  <p class="sous-titre">Trois épreuves, un même départ. Inscription en ligne, dossard attribué dès le paiement.</p>

  <div class="epreuves">
    @foreach ($epreuves as $epreuve)
      @php($occupees = $epreuve->placesOccupees())
      @php($complet = $occupees >= $epreuve->quota)
      <section class="carte epreuve">
        <h2>{{ $epreuve->nom }}</h2>
        <div class="chiffres">
          <span>{{ str_replace('.', ',', (string) (float) $epreuve->distance_km) }} km</span>
          <span>{{ $epreuve->denivele_m }} m D+</span>
          <span>départ {{ $epreuve->depart_le->translatedFormat('j F à H\hi') }}</span>
          <span>dès {{ $epreuve->age_minimum }} ans</span>
        </div>
        <div class="prix">{{ $epreuve->prixEnEuros() }}</div>
        <div class="jauge {{ $complet ? 'pleine' : '' }}" aria-hidden="true"><span style="width: {{ min(100, round($occupees * 100 / $epreuve->quota)) }}%"></span></div>
        <p class="discret">
          @if ($complet)
            Complet ({{ $epreuve->quota }} places) · {{ $epreuve->enListeAttente() }} sur liste d'attente
          @else
            {{ $epreuve->quota - $occupees }} places restantes sur {{ $epreuve->quota }}
          @endif
        </p>
        @if ($epreuve->inscriptionsOuvertes())
          <a class="bouton {{ $complet ? 'secondaire' : '' }}" href="{{ route('inscription.formulaire', $epreuve) }}">
            {{ $complet ? 'Rejoindre la liste d\'attente' : 'S\'inscrire' }}
          </a>
        @else
          <p><strong>Inscriptions fermées.</strong></p>
        @endif
      </section>
    @endforeach
  </div>
@endsection
