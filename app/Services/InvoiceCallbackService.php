<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceGenerationFailedNotification;
use App\Notifications\InvoicePdfReadyNotification;
use Illuminate\Support\Facades\Notification;

class InvoiceCallbackService
{
    /**
     * Handle the callback from the Go PDF worker.
     *
     * On success: marks the invoice completed, stores the pdf_path and timestamp.
     * On failure: marks the invoice failed and notifies the freelancer.
     */
    public function handle(Invoice $invoice, string $status, ?string $pdfPath = null, ?string $error = null): void
    {
        if ($status === InvoiceStatus::Completed->value) {
            $invoice->update([
                'status' => InvoiceStatus::Completed,
                'pdf_path' => $pdfPath,
                'pdf_generated_at' => now(),
            ]);

            $invoice->client->notify(new InvoicePdfReadyNotification($invoice));

            return;
        }

        $invoice->update(['status' => InvoiceStatus::Failed]);

        Notification::route('mail', config('mail.from.address'))
            ->notify(new InvoiceGenerationFailedNotification($invoice, $error));
    }
}
