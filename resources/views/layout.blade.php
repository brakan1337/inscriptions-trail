<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('titre', 'Inscriptions') · Trail des crêtes</title>
  <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
  <header class="entete">
    <a class="marque" href="{{ route('accueil') }}">Trail des crêtes<small>Course fictive · inscriptions en ligne</small></a>
    <nav>
      @auth
        <a href="{{ route('organisation.tableau') }}">Tableau de bord</a>
        <a href="{{ route('organisation.inscrits') }}">Inscrits</a>
        <form method="post" action="{{ route('organisation.deconnexion') }}">@csrf<button class="lien">Déconnexion</button></form>
      @else
        <a href="{{ route('accueil') }}">Les épreuves</a>
        <a href="{{ route('login') }}">Organisation</a>
      @endauth
    </nav>
  </header>
  <main>
    @if (session('succes'))
      <div class="bandeau ok" role="status">{{ session('succes') }}</div>
    @endif
    @yield('contenu')
  </main>
</body>
</html>
