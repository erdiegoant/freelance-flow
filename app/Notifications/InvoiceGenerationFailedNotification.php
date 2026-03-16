<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceGenerationFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $error = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("PDF generation failed for invoice {$this->invoice->invoice_number}")
            ->markdown('emails.invoices.generation-failed', [
                'invoice' => $this->invoice,
                'error' => $this->error,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'error' => $this->error,
        ];
    }
}
