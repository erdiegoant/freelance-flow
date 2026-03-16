<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Notifications\InvoiceGenerationFailedNotification;
use App\Notifications\InvoicePdfReadyNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['services.invoice_worker.callback_secret' => 'test-callback-secret']);
});

describe('POST /api/invoices/{invoice}/callback', function () {
    it('marks invoice as completed and stores pdf_path on success', function () {
        Notification::fake();

        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'completed',
            'pdf_path' => 'invoices/INV-2026-0001.pdf',
        ], ['X-Callback-Secret' => 'test-callback-secret'])
            ->assertSuccessful()
            ->assertJsonPath('message', 'Callback processed.');

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::Completed)
            ->and($invoice->pdf_path)->toBe('invoices/INV-2026-0001.pdf')
            ->and($invoice->pdf_generated_at)->not->toBeNull();
    });

    it('sends pdf ready notification to client on success', function () {
        Notification::fake();

        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'completed',
            'pdf_path' => 'invoices/INV-2026-0001.pdf',
        ], ['X-Callback-Secret' => 'test-callback-secret'])->assertSuccessful();

        Notification::assertSentTo($invoice->client, InvoicePdfReadyNotification::class);
    });

    it('marks invoice as failed on failure callback', function () {
        Notification::fake();

        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'failed',
            'error' => 'Renderer crashed.',
        ], ['X-Callback-Secret' => 'test-callback-secret'])->assertSuccessful();

        expect($invoice->refresh()->status)->toBe(InvoiceStatus::Failed);
    });

    it('sends failure notification to freelancer on failure', function () {
        Notification::fake();

        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'failed',
        ], ['X-Callback-Secret' => 'test-callback-secret'])->assertSuccessful();

        Notification::assertSentOnDemand(InvoiceGenerationFailedNotification::class);
    });

    it('rejects request with wrong callback secret', function () {
        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'completed',
            'pdf_path' => 'invoices/foo.pdf',
        ], ['X-Callback-Secret' => 'wrong-secret'])->assertForbidden();
    });

    it('rejects request with missing callback secret header', function () {
        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create();

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'completed',
            'pdf_path' => 'invoices/foo.pdf',
        ])->assertForbidden();
    });

    it('requires pdf_path when status is completed', function () {
        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['status' => InvoiceStatus::Processing]);

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'completed',
        ], ['X-Callback-Secret' => 'test-callback-secret'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pdf_path');
    });

    it('rejects invalid status value', function () {
        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create();

        $this->postJson("/api/invoices/{$invoice->id}/callback", [
            'status' => 'unknown',
        ], ['X-Callback-Secret' => 'test-callback-secret'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });
});
