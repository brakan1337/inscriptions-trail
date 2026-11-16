<?php

namespace App\Mail;

use App\Models\Inscription;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InscriptionConfirmee extends Mailable
{
    public function __construct(public Inscription $inscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Inscription confirmée : dossard {$this->inscription->dossard}, {$this->inscription->epreuve->nom}");
    }

    public function content(): Content
    {
        return new Content(text: 'mails.inscription-confirmee');
    }
}
