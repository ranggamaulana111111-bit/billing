<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder Pembayaran - '.$this->invoice->invoice_code,
        );
    }

    public function content(): Content
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return new Content(
            view: 'emails.invoice-reminder',
            with: ['invoice' => $this->invoice, 'settings' => $settings],
        );
    }
}
