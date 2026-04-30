<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InventoryReconcileAlert extends Mailable
{
    public function __construct(
        public readonly string $body,
        string $subject = '[FoodBank] Inventory reconciliation alert — gaps detected',
    ) {
        $this->subject($subject);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.inventory-reconcile-alert');
    }
}
