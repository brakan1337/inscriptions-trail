@extends('layout')
@section('titre', 'Paiement')
@section('contenu')
  <div class="bandeau info">Paiement simulé : aucune carte n'est demandée et aucun argent ne circule. En production, cette page est celle du prestataire de paiement choisi par le club.</div>
  <section class="carte">
    <h1>Paiement</h1>
    <p>{{ $inscription->epreuve->nom }} · {{ $inscription->nomComplet() }}</p>
    <p class="prix">{{ number_format($inscription->montant_centimes / 100, 2, ',', ' ') }} €</p>
    <form method="post" action="{{ $confirmer }}" style="display:inline">
      @csrf
      <button type="submit">Simuler un paiement réussi</button>
    </form>
    <a class="bouton secondaire" href="{{ route('inscriptions.show', $inscription) }}">Abandonner</a>
  </section>
@endsection
