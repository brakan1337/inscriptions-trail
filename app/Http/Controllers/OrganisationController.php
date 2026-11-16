<?php

namespace App\Http\Controllers;

use App\Enums\StatutInscription;
use App\Enums\StatutJustificatif;
use App\Models\Epreuve;
use App\Models\Inscription;
use App\Services\Guichet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Le back-office de l'organisateur : remplissage, justificatifs, annulations, export du chronométreur. */
class OrganisationController extends Controller
{
    public function connexion(): View
    {
        return view('organisation.connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $identifiants = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($identifiants)) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('organisation.tableau'));
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('accueil');
    }

    public function tableau(): View
    {
        $epreuves = Epreuve::query()->orderBy('depart_le')->orderByDesc('distance_km')->get()->map(function (Epreuve $e) {
            $parStatut = $e->inscriptions()->selectRaw('statut, count(*) as n')->groupBy('statut')->pluck('n', 'statut');

            return (object) [
                'epreuve' => $e,
                'payees' => (int) ($parStatut[StatutInscription::Payee->value] ?? 0),
                'reservees' => $e->placesOccupees() - (int) ($parStatut[StatutInscription::Payee->value] ?? 0),
                'attente' => (int) ($parStatut[StatutInscription::ListeAttente->value] ?? 0),
                'aVerifier' => $e->inscriptions()->where('statut', StatutInscription::Payee)
                    ->where('justificatif_statut', StatutJustificatif::AVerifier)->whereNotNull('justificatif_numero')->count(),
                'ppsAFournir' => $e->inscriptions()->where('statut', StatutInscription::Payee)->whereNull('justificatif_numero')->count(),
                'recette' => (int) $e->inscriptions()->where('statut', StatutInscription::Payee)->sum('montant_centimes'),
            ];
        });

        return view('organisation.tableau', ['lignes' => $epreuves]);
    }

    public function inscrits(Request $request): View
    {
        $filtres = $request->validate([
            'epreuve' => ['nullable', 'exists:epreuves,id'],
            'statut' => ['nullable', 'string'],
            'justificatif' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $inscriptions = Inscription::query()->with('epreuve')
            ->when($filtres['epreuve'] ?? null, fn ($q, $id) => $q->where('epreuve_id', $id))
            ->when($filtres['statut'] ?? null, fn ($q, $s) => $q->where('statut', $s))
            ->when($filtres['justificatif'] ?? null, fn ($q, $j) => $q->where('justificatif_statut', $j))
            ->when($filtres['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$t}%")->orWhere('prenom', 'like', "%{$t}%")
                ->orWhere('reference', $t)->orWhere('dossard', ctype_digit($t) ? (int) $t : -1)))
            ->orderBy('epreuve_id')->orderByRaw('dossard is null')->orderBy('dossard')->orderBy('position_attente')->orderBy('nom')
            ->paginate(40)->withQueryString();

        return view('organisation.inscrits', [
            'inscriptions' => $inscriptions,
            'epreuves' => Epreuve::query()->orderByDesc('distance_km')->get(),
            'statuts' => StatutInscription::cases(),
            'statutsJustificatif' => StatutJustificatif::cases(),
            'filtres' => $filtres,
        ]);
    }

    public function justificatif(Request $request, Inscription $inscription, Guichet $guichet): RedirectResponse
    {
        $choix = $request->validate([
            'decision' => ['required', 'in:valider,refuser'],
            'motif' => ['required_if:decision,refuser', 'nullable', 'string', 'max:200'],
        ], ['motif.required_if' => 'Indiquez au coureur pourquoi son justificatif est refusé.']);

        $choix['decision'] === 'valider'
            ? $guichet->validerJustificatif($inscription)
            : $guichet->refuserJustificatif($inscription, $choix['motif']);

        return back()->with('succes', "Justificatif de {$inscription->nomComplet()} : ".($choix['decision'] === 'valider' ? 'validé.' : 'refusé.'));
    }

    public function annuler(Inscription $inscription, Guichet $guichet): RedirectResponse
    {
        $guichet->annuler($inscription);

        return back()->with('succes', "Inscription de {$inscription->nomComplet()} annulée. La place est proposée à la liste d'attente.");
    }

    /**
     * Le fichier attendu par le chronométreur : un coureur payé par ligne, trié par dossard.
     * Séparateur point-virgule et BOM UTF-8 : il s'ouvre correctement dans un Excel français.
     */
    public function export(Epreuve $epreuve): StreamedResponse
    {
        $nomFichier = "chronometrage-{$epreuve->slug}.csv";

        return response()->streamDownload(function () use ($epreuve) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\u{FEFF}");
            fputcsv($sortie, ['dossard', 'nom', 'prenom', 'sexe', 'naissance', 'categorie', 'club', 'epreuve'], ';', '"', '');
            $epreuve->inscriptions()->where('statut', StatutInscription::Payee)->orderBy('dossard')
                ->each(function (Inscription $i) use ($sortie, $epreuve) {
                    $i->setRelation('epreuve', $epreuve);
                    fputcsv($sortie, [
                        $i->dossard, mb_strtoupper($i->nom), $i->prenom, $i->sexe, $i->naissance->format('d/m/Y'),
                        $i->categorie(), $i->club ?? '', $epreuve->nom,
                    ], ';', '"', '');
                });
            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
