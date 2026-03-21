<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum');
    // Provide a predictable invoice number so tests don't depend on the DB sequence
    $this->mock(InvoiceNumberService::class)
        ->shouldReceive('generate')
        ->andReturn('INV-2026-0001');

    // Silence the Redis push in tests that don't assert on it
    $conn = Mockery::mock();
    $conn->shouldReceive('rpush');
    Redis::shouldReceive('connection')->with('go_worker')->andReturn($conn);
});

describe('POST /api/projects/{project}/invoices', function () {
    it('generates an invoice and returns 202 with processing status', function () {
        $project = Project::factory()->create(['hourly_rate' => 75]);
        TimeLog::factory(3)->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 2]);

        $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'tax_rate' => 0.19,
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.attributes.status', 'processing')
            ->assertJsonPath('data.attributes.invoice_number', 'INV-2026-0001');
    });

    it('stores the invoice in the database', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-02-15', 'hours' => 4]);

        $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ])->assertStatus(202);

        $this->assertDatabaseHas('invoices', [
            'project_id' => $project->id,
            'invoice_number' => 'INV-2026-0001',
            'status' => 'processing',
        ]);
    });

    it('creates invoice items for each time log', function () {
        $project = Project::factory()->create(['hourly_rate' => 75]);
        TimeLog::factory(2)->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 3]);

        $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ])->assertStatus(202);

        $this->assertDatabaseCount('invoice_items', 2);
    });

    it('calculates totals correctly with tax', function () {
        // 3 logs × 2 hours × $75 = $450 subtotal
        $project = Project::factory()->create(['hourly_rate' => 75]);
        TimeLog::factory(3)->for($project)->create(['logged_at' => '2026-03-01', 'hours' => 2]);

        $response = $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'tax_rate' => 0.19,
        ])->assertStatus(202)->json();

        expect((float) $response['data']['attributes']['subtotal'])->toBe(450.0)
            ->and((float) $response['data']['attributes']['tax_amount'])->toBe(85.5)
            ->and((float) $response['data']['attributes']['total'])->toBe(535.5);
    });

    it('only includes time logs within the date range', function () {
        $project = Project::factory()->create(['hourly_rate' => 100]);
        TimeLog::factory()->for($project)->create(['logged_at' => '2026-01-15', 'hours' => 3]); // inside range
        TimeLog::factory()->for($project)->create(['logged_at' => '2025-12-31', 'hours' => 8]); // outside range

        $response = $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ])->assertStatus(202)->json();

        // Only the in-range log: 3 hours × $100 = $300
        expect((float) $response['data']['attributes']['subtotal'])->toBe(300.0);
    });

    it('returns 422 when no unbilled time logs exist in range', function () {
        $project = Project::factory()->create();

        $this->postJson("/api/projects/{$project->id}/invoices", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ])->assertUnprocessable()->assertJsonPath('message', 'No unbilled time logs found for the given date range.');
    });

    dataset('invalid invoice payloads', [
        'missing start_date' => [['end_date' => '2026-03-31'], 'start_date'],
        'missing end_date' => [['start_date' => '2026-01-01'], 'end_date'],
        'end before start' => [['start_date' => '2026-03-31', 'end_date' => '2026-01-01'], 'end_date'],
        'tax_rate above 1' => [['start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'tax_rate' => 1.5], 'tax_rate'],
    ]);

    it('rejects invalid payload with 422', function (array $payload, string $errorField) {
        $project = Project::factory()->create();

        $this->postJson("/api/projects/{$project->id}/invoices", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid invoice payloads');
});

describe('GET /api/projects/{project}/invoices', function () {
    it('lists invoices for a project', function () {
        $project = Project::factory()->create();
        Invoice::factory(3)->for($project)->create();

        $this->getJson("/api/projects/{$project->id}/invoices")
            ->assertSuccessful()
            ->assertJsonCount(3, 'data');
    });

    it('returns an empty list when the project has no invoices', function () {
        $project = Project::factory()->create();

        $this->getJson("/api/projects/{$project->id}/invoices")
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');
    });

    it('does not include invoices from other projects', function () {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        Invoice::factory(2)->for($project)->create();
        Invoice::factory()->for($other)->create();

        $this->getJson("/api/projects/{$project->id}/invoices")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');
    });
});

describe('GET /api/invoices/{invoice}', function () {
    it('returns invoice with relationships', function () {
        $invoice = Invoice::factory()
            ->for(Project::factory()->create())
            ->create(['invoice_number' => 'INV-2026-0042', 'status' => InvoiceStatus::Pending]);

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.invoice_number', 'INV-2026-0042')
            ->assertJsonPath('data.attributes.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'type', 'attributes' => ['invoice_number', 'status', 'subtotal', 'total', 'due_date']]]);
    });

    it('returns 404 for unknown invoice', function () {
        $this->getJson('/api/invoices/999')->assertNotFound();
    });
});
