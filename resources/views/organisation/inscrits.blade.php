@extends('layout')
@section('titre', 'Inscrits')
@section('contenu')
  <h1>Inscrits</h1>
  <p class="sous-titre">{{ $inscriptions->total() }} inscription(s) correspondent aux filtres.</p>

  @if ($errors->any())<div class="bandeau alerte" role="alert">{{ $errors->first() }}</div>@endif

  <form method="get" class="filtres">
    <div><label for="epreuve">Épreuve</label>
      <select id="epreuve" name="epreuve"><option value="">Toutes</option>
        @foreach ($epreuves as $e)<option value="{{ $e->id }}" @selected(($filtres['epreuve'] ?? '') == $e->id)>{{ $e->nom }}</option>@endforeach
      </select></div>
    <div><label for="statut">Statut</label>
      <select id="statut" name="statut"><option value="">Tous</option>
        @foreach ($statuts as $s)<option value="{{ $s->value }}" @selected(($filtres['statut'] ?? '') === $s->value)>{{ $s->libelle() }}</option>@endforeach
      </select></div>
    <div><label for="justificatif">Justificatif</label>
      <select id="justificatif" name="justificatif"><option value="">Tous</option>
        @foreach ($statutsJustificatif as $j)<option value="{{ $j->value }}" @selected(($filtres['justificatif'] ?? '') === $j->value)>{{ $j->libelle() }}</option>@endforeach
      </select></div>
    <div><label for="q">Nom, dossard ou référence</label><input id="q" name="q" value="{{ $filtres['q'] ?? '' }}"></div>
    <div><button type="submit">Filtrer</button></div>
  </form>

  <section class="carte defilement">
    <table>
      <thead><tr><th>Dossard</th><th>Coureur</th><th>Épreuve</th><th>Statut</th><th>Justificatif</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($inscriptions as $i)
          <tr>
            <td class="nombre">{{ $i->dossard ?? ($i->position_attente ? 'att. '.$i->position_attente : '—') }}</td>
            <td><strong>{{ $i->nomComplet() }}</strong><br><span class="discret">{{ $i->categorie() }} · {{ $i->sexe }} · {{ $i->club ?? 'sans club' }}</span></td>
            <td>{{ $i->epreuve->nom }}</td>
            <td><span class="statut {{ $i->statut->value }}">{{ $i->statut->libelle() }}</span></td>
            <td>
              {{ $i->justificatif_type === \App\Enums\Justificatif::Pps ? 'PPS' : 'Licence' }} {{ $i->justificatif_numero ?? 'à fournir' }}
              @if ($i->justificatif_date)<br><span class="discret">du {{ $i->justificatif_date->format('d/m/Y') }}</span>@endif
              @unless ($i->ppsAFournir())
                <br><span class="statut {{ $i->justificatif_statut->value }}">{{ $i->justificatif_statut->libelle() }}</span>
              @endunless
            </td>
            <td>
              <div class="actions-ligne">
                @if ($i->justificatif_statut !== \App\Enums\StatutJustificatif::Valide && ! $i->ppsAFournir())
                  <form method="post" action="{{ route('organisation.justificatif', $i) }}">@csrf
                    <input type="hidden" name="decision" value="valider"><button class="petit">Valider</button></form>
                @endif
                @if ($i->justificatif_statut === \App\Enums\StatutJustificatif::AVerifier && ! $i->ppsAFournir())
                  <form method="post" action="{{ route('organisation.justificatif', $i) }}" class="actions-ligne">@csrf
                    <input type="hidden" name="decision" value="refuser">
                    <input name="motif" placeholder="Motif du refus" aria-label="Motif du refus">
                    <button class="petit secondaire">Refuser</button></form>
                @endif
                @if (in_array($i->statut, [\App\Enums\StatutInscription::Payee, \App\Enums\StatutInscription::ListeAttente, \App\Enums\StatutInscription::EnAttentePaiement, \App\Enums\StatutInscription::OffreEnCours], true))
                  <form method="post" action="{{ route('organisation.annuler', $i) }}" onsubmit="return confirm('Annuler l\'inscription de {{ e($i->nomComplet()) }} ?')">@csrf
                    <button class="petit danger">Annuler</button></form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6">Aucune inscription.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="pagination">{{ $inscriptions->links() }}</div>
  </section>
@endsection
