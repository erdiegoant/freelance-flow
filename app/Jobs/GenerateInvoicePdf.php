<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class GenerateInvoicePdf implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    /**
     * Push a structured JSON payload to the Redis queue consumed by the Go PDF worker.
     *
     * The Go worker reads from `queues:invoice_generation`. Each payload contains
     * everything the worker needs to render the PDF and call back when done.
     */
    public function handle(): void
    {
        $invoice = $this->invoice->load(['client', 'project', 'items']);

        $payload = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client' => [
                'name' => $invoice->client->name,
                'email' => $invoice->client->email,
                'company_name' => $invoice->client->company_name,
                'address' => $invoice->client->address,
                'tax_id' => $invoice->client->tax_id,
            ],
            'project' => [
                'name' => $invoice->project->name,
                'hourly_rate' => (float) $invoice->project->hourly_rate,
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->toArray(),
            'subtotal' => (float) $invoice->subtotal,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_amount' => (float) $invoice->tax_amount,
            'total' => (float) $invoice->total,
            'due_date' => $invoice->due_date->toDateString(),
            'callback_url' => url("/api/invoices/{$invoice->id}/callback"),
            'callback_secret' => config('services.invoice_worker.callback_secret'),
        ];

        Redis::rpush('queues:invoice_generation', json_encode($payload));
    }
}
