@extends('layout')
@section('titre', 'Connexion')
@section('contenu')
  <form method="post" action="{{ route('login') }}" class="carte" style="max-width:420px;margin:24px auto">
    @csrf
    <h1>Organisation</h1>
    <p class="sous-titre">Réservé à l'équipe d'organisation.</p>
    <div class="champ">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
      @error('email')<p class="erreur">{{ $message }}</p>@enderror
    </div>
    <div class="champ">
      <label for="password">Mot de passe</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit">Se connecter</button>
  </form>
@endsection
