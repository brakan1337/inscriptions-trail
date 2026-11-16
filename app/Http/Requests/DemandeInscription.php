<?php

namespace App\Http\Requests;

use App\Enums\Justificatif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemandeInscription extends FormRequest
{
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'sexe' => ['required', Rule::in(['F', 'H'])],
            'naissance' => ['required', 'date', 'before:today', 'after:1920-01-01'],
            'email' => ['required', 'email', 'max:190'],
            'club' => ['nullable', 'string', 'max:120'],
            'justificatif_type' => ['required', Rule::enum(Justificatif::class)],
            'justificatif_numero' => ['required_if:justificatif_type,licence', 'nullable', 'string', 'max:40'],
            // Pour une licence, la date n'a pas de sens : le champ est écarté avant toute autre règle.
            'justificatif_date' => ['exclude_if:justificatif_type,licence', 'required_with:justificatif_numero', 'nullable', 'date', 'before_or_equal:today'],
            'reglement' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Ce champ est obligatoire.',
            'max' => 'Ce champ est trop long.',
            'email' => 'Cette adresse e-mail n\'est pas valide.',
            'date' => 'Cette date n\'est pas valide.',
            'naissance.before' => 'La date de naissance doit être passée.',
            'naissance.after' => 'Cette date de naissance n\'est pas vraisemblable.',
            'sexe.in' => 'Choisissez F ou H.',
            'justificatif_numero.required_if' => 'Indiquez votre numéro de licence.',
            'justificatif_date.required_with' => 'Indiquez la date à laquelle vous avez obtenu votre PPS.',
            'justificatif_date.before_or_equal' => 'La date du PPS ne peut pas être dans le futur.',
            'reglement.accepted' => 'Vous devez accepter le règlement de la course.',
        ];
    }

    /** Les données utiles au guichet, sans la case du règlement. */
    public function donnees(): array
    {
        return collect($this->validated())->except('reglement')->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();
    }
}
