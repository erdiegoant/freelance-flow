<?php

use App\Jobs\GenerateInvoicePdf;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use Illuminate\Support\Facades\Redis;

describe('GenerateInvoicePdf job', function () {
    it('pushes a JSON payload to the invoice_generation Redis queue', function () {
        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function (string $key, string $payload) {
                expect($key)->toBe('queues:invoice_generation');
                $data = json_decode($payload, true);
                expect($data)->toBeArray()->toHaveKey('invoice_id');

                return true;
            });

        $invoice = Invoice::factory()
            ->for(Project::factory()->create(['hourly_rate' => 75]))
            ->for(Client::factory()->create())
            ->create(['invoice_number' => 'INV-2026-JOB']);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'Test work',
            'quantity' => 2,
            'unit_price' => 75,
            'total' => 150,
        ]);

        (new GenerateInvoicePdf($invoice))->handle();
    });

    it('includes all required payload fields', function () {
        $capturedPayload = null;

        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function (string $key, string $payload) use (&$capturedPayload) {
                $capturedPayload = json_decode($payload, true);

                return true;
            });

        $client = Client::factory()->create([
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'company_name' => 'Test Co',
            'tax_id' => '123456789',
        ]);

        $project = Project::factory()->for($client)->create([
            'name' => 'Test Project',
            'hourly_rate' => 100,
        ]);

        $invoice = Invoice::factory()->for($project)->for($client)->create([
            'invoice_number' => 'INV-2026-PAY',
            'subtotal' => 200,
            'tax_rate' => 0.19,
            'tax_amount' => 38,
            'total' => 238,
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'Design work',
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);

        (new GenerateInvoicePdf($invoice))->handle();

        expect($capturedPayload)
            ->toHaveKey('invoice_id', $invoice->id)
            ->toHaveKey('invoice_number', 'INV-2026-PAY')
            ->toHaveKey('callback_url')
            ->toHaveKey('callback_secret')
            ->toHaveKey('items')
            ->and($capturedPayload['client']['name'])->toBe('Test Client')
            ->and($capturedPayload['client']['email'])->toBe('client@test.com')
            ->and($capturedPayload['project']['name'])->toBe('Test Project')
            ->and($capturedPayload['project']['hourly_rate'])->toBeNumeric()
            ->and($capturedPayload['items'])->toHaveCount(1)
            ->and($capturedPayload['items'][0]['description'])->toBe('Design work');
    });

    it('serialises numeric values as floats not strings', function () {
        $capturedPayload = null;

        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function (string $key, string $payload) use (&$capturedPayload) {
                $capturedPayload = json_decode($payload, true);

                return true;
            });

        $invoice = Invoice::factory()
            ->for(Project::factory()->create(['hourly_rate' => 75]))
            ->for(Client::factory()->create())
            ->create(['subtotal' => 300, 'tax_rate' => 0.19, 'tax_amount' => 57, 'total' => 357]);

        InvoiceItem::factory()->for($invoice)->create(['quantity' => 4, 'unit_price' => 75, 'total' => 300]);

        (new GenerateInvoicePdf($invoice))->handle();

        // Numbers must be JSON numbers (not strings) — PHP json_decode returns int for whole numbers
        expect($capturedPayload['subtotal'])->toBeNumeric()->not->toBeString()
            ->and($capturedPayload['tax_rate'])->toBeNumeric()->not->toBeString()
            ->and($capturedPayload['project']['hourly_rate'])->toBeNumeric()->not->toBeString();
    });
});
