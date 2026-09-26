<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderInvoiceMail extends Mailable
{
    public function __construct(public array $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Hóa đơn {$this->invoice['invoice_no']} — ShopTech",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: ['invoice' => $this->invoice],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                "{$this->invoice['invoice_no']}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
