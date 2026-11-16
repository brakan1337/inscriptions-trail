@extends('layout')
@section('titre', 'Inscription · '.$epreuve->nom)
@section('contenu')
  <h1>Inscription : {{ $epreuve->nom }}</h1>
  <p class="sous-titre">{{ $epreuve->prixEnEuros() }} · départ le {{ $epreuve->depart_le->translatedFormat('l j F Y à H\hi') }} · à partir de {{ $epreuve->age_minimum }} ans</p>

  @php($libres = $epreuve->placesLibres())
  @if ($libres === 0)
    <div class="bandeau info">L'épreuve est complète. Votre demande rejoindra la liste d'attente : si une place se libère, elle vous sera proposée par e-mail.</div>
  @else
    <div class="bandeau ok">Encore {{ $libres }} place(s). La vôtre sera réservée {{ \App\Services\Guichet::DELAI_PAIEMENT_MINUTES }} minutes, le temps de payer.</div>
  @endif

  @error('epreuve')<div class="bandeau alerte" role="alert">{{ $message }}</div>@enderror

  <form method="post" action="{{ route('inscription.enregistrer', $epreuve) }}" class="carte" novalidate>
    @csrf
    <fieldset>
      <legend>Le coureur</legend>
      <div class="deux">
        <div class="champ">
          <label for="prenom">Prénom</label>
          <input id="prenom" name="prenom" value="{{ old('prenom') }}" autocomplete="given-name" required>
          @error('prenom')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <label for="nom">Nom</label>
          <input id="nom" name="nom" value="{{ old('nom') }}" autocomplete="family-name" required>
          @error('nom')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <label for="naissance">Date de naissance</label>
          <input id="naissance" name="naissance" type="date" value="{{ old('naissance') }}" required>
          @error('naissance')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <span id="sexe-titre" style="display:block;font-weight:600;margin-bottom:4px">Classement</span>
          <div class="choix" role="radiogroup" aria-labelledby="sexe-titre">
            <label><input type="radio" name="sexe" value="F" @checked(old('sexe') === 'F')> Femmes</label>
            <label><input type="radio" name="sexe" value="H" @checked(old('sexe') === 'H')> Hommes</label>
          </div>
          @error('sexe')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
          @error('email')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <label for="club">Club (facultatif)</label>
          <input id="club" name="club" value="{{ old('club') }}">
          @error('club')<p class="erreur">{{ $message }}</p>@enderror
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Licence ou PPS</legend>
      <p class="discret">Sans licence d'athlétisme, il faut un PPS (Parcours Prévention Santé), obtenu en ligne. Il est valable trois mois et doit l'être encore le jour de la course :
        si vous l'obtenez avant le {{ \App\Services\Guichet::debutFenetrePps($epreuve)->format('d/m/Y') }}, il aura expiré.
        Laissez alors ces champs vides et ajoutez-le plus tard depuis votre page de suivi.</p>
      <div class="choix champ">
        @foreach ($justificatifs as $j)
          <label><input type="radio" name="justificatif_type" value="{{ $j->value }}" @checked(old('justificatif_type', 'pps') === $j->value)> {{ $j->libelle() }}</label>
        @endforeach
      </div>
      @error('justificatif_type')<p class="erreur">{{ $message }}</p>@enderror
      <div class="deux">
        <div class="champ">
          <label for="justificatif_numero">Numéro de licence ou de PPS</label>
          <input id="justificatif_numero" name="justificatif_numero" value="{{ old('justificatif_numero') }}" required>
          @error('justificatif_numero')<p class="erreur">{{ $message }}</p>@enderror
        </div>
        <div class="champ">
          <label for="justificatif_date">Date d'obtention du PPS</label>
          <input id="justificatif_date" name="justificatif_date" type="date" value="{{ old('justificatif_date') }}">
          @error('justificatif_date')<p class="erreur">{{ $message }}</p>@enderror
        </div>
      </div>
    </fieldset>

    <div class="champ">
      <label style="font-weight:400;display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="reglement" value="1" @checked(old('reglement'))> J'accepte le règlement de la course.
      </label>
      @error('reglement')<p class="erreur">{{ $message }}</p>@enderror
    </div>

    <button type="submit">{{ $libres === 0 ? 'Rejoindre la liste d\'attente' : 'Réserver ma place' }}</button>
  </form>
@endsection
