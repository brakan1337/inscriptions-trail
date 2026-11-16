@extends('layout')
@section('titre', 'Tableau de bord')
@section('contenu')
  <h1>Tableau de bord</h1>
  <p class="sous-titre">Le remplissage de chaque épreuve, en direct.</p>

  <div class="epreuves">
    @foreach ($lignes as $l)
      @php($e = $l->epreuve)
      <section class="carte">
        <h2>{{ $e->nom }}</h2>
        <div class="jauge {{ $l->payees + $l->reservees >= $e->quota ? 'pleine' : '' }}" aria-hidden="true">
          <span style="width: {{ min(100, round(($l->payees + $l->reservees) * 100 / $e->quota)) }}%"></span>
        </div>
        <table>
          <tbody>
            <tr><td>Payées</td><td class="nombre"><strong>{{ $l->payees }}</strong> / {{ $e->quota }}</td></tr>
            <tr><td>Places en cours de paiement</td><td class="nombre">{{ $l->reservees }}</td></tr>
            <tr><td>Liste d'attente</td><td class="nombre">{{ $l->attente }}</td></tr>
            <tr><td>Justificatifs à vérifier</td>
              <td class="nombre">
                @if ($l->aVerifier)
                  <a href="{{ route('organisation.inscrits', ['epreuve' => $e->id, 'statut' => 'payee', 'justificatif' => 'a_verifier']) }}">{{ $l->aVerifier }}</a>
                @else 0 @endif
              </td></tr>
            <tr><td>PPS encore à fournir</td><td class="nombre">{{ $l->ppsAFournir }}</td></tr>
            <tr><td>Recette</td><td class="nombre">{{ number_format($l->recette / 100, 0, ',', ' ') }} €</td></tr>
          </tbody>
        </table>
        <p style="margin:14px 0 0">
          <a class="bouton secondaire petit" href="{{ route('organisation.export', $e) }}">Export chronométreur (CSV)</a>
        </p>
      </section>
    @endforeach
  </div>
@endsection
