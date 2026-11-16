<?php

namespace App\Mail;

use App\Models\Inscription;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PlaceProposee extends Mailable
{
    public function __construct(public Inscription $inscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Une place s'est libérée : {$this->inscription->epreuve->nom}");
    }

    public function content(): Content
    {
        return new Content(text: 'mails.place-proposee');
    }
}
