<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class InvoicePdfReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
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
        $downloadUrl = URL::temporarySignedRoute(
            'invoices.download',
            now()->addHours(48),
            ['invoice' => $this->invoice->id],
        );

        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} is ready")
            ->markdown('emails.invoices.pdf-ready', [
                'invoice' => $this->invoice,
                'downloadUrl' => $downloadUrl,
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
        ];
    }
}
