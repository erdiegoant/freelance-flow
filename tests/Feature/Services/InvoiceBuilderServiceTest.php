<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\TimeLog;
use App\Services\InvoiceBuilderService;
use App\Services\InvoiceNumberService;
use Carbon\Carbon;

beforeEach(function () {
    $this->mock(InvoiceNumberService::class)
        ->shouldReceive('generate')
        ->andReturn('INV-2026-TEST');
});

describe('InvoiceBuilderService::build()', function () {
    it('creates an invoice with the correct totals', function () {
        // 2 logs × 3 hours × $50 = $300 subtotal
        $project = Project::factory()->create(['hourly_rate' => 50]);
        TimeLog::factory(2)->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 3]);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect((float) $invoice->subtotal)->toBe(300.0)
            ->and((float) $invoice->total)->toBe(300.0)
            ->and((float) $invoice->tax_amount)->toBe(0.0);
    });

    it('applies tax correctly', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 4]);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
            taxRate: 0.19,
        );

        // 4 hours × $100 = $400 subtotal, 19% tax = $76, total = $476
        expect((float) $invoice->subtotal)->toBe(400.0)
            ->and((float) $invoice->tax_amount)->toBe(76.0)
            ->and((float) $invoice->total)->toBe(476.0)
            ->and((float) $invoice->tax_rate)->toBe(0.19);
    });

    it('creates an invoice item for each time log', function () {
        $project = Project::factory()->create(['hourly_rate' => 75]);
        TimeLog::factory(3)->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 2]);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->items)->toHaveCount(3);

        $invoice->items->each(function ($item) {
            expect((float) $item->quantity)->toBe(2.0)
                ->and((float) $item->unit_price)->toBe(75.0)
                ->and((float) $item->total)->toBe(150.0);
        });
    });

    it('links invoice items to their time logs', function () {
        $project = Project::factory()->create(['hourly_rate' => 80]);
        $timeLog = TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-01']);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->items->first()->time_log_id)->toBe($timeLog->id);
    });

    it('creates the invoice with pending status', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-01']);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->status)->toBe(InvoiceStatus::Pending);
    });

    it('only includes time logs within the date range', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-02-15', 'hours' => 5]); // inside
        TimeLog::factory()->for($project)->create(['logged_at' => '2025-12-31', 'hours' => 8]); // outside

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->items)->toHaveCount(1)
            ->and((float) $invoice->subtotal)->toBe(500.0);
    });

    it('excludes time logs already attached to an invoice', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        $billedLog = TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 8]);
        $unbilledLog = TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-02', 'hours' => 2]);

        // Bill the first log via an existing invoice item
        $existingInvoice = Invoice::factory()->for($project)->for($project->client)->create();
        InvoiceItem::factory()->for($existingInvoice)->create(['time_log_id' => $billedLog->id]);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->items)->toHaveCount(1)
            ->and($invoice->items->first()->time_log_id)->toBe($unbilledLog->id);
    });

    it('throws RuntimeException when no unbilled time logs exist', function () {
        $project = Project::factory()->create();

        expect(fn () => app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        ))->toThrow(RuntimeException::class, 'No unbilled time logs found for the given date range.');
    });

    it('assigns the correct client to the invoice', function () {
        $project = Project::factory()->create(['hourly_rate' => 50]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-03-01']);

        $invoice = app(InvoiceBuilderService::class)->build(
            project: $project,
            startDate: Carbon::parse('2026-01-01'),
            endDate: Carbon::parse('2026-03-31'),
        );

        expect($invoice->client_id)->toBe($project->client_id);
    });
});
