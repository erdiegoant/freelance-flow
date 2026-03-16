<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InvoiceBuilderService
{
    public function __construct(
        private readonly InvoiceNumberService $invoiceNumberService,
    ) {}

    /**
     * Build an invoice from all unbilled time logs for a project within the given date range.
     *
     * @throws \RuntimeException when no unbilled time logs exist for the range
     */
    public function build(Project $project, Carbon $startDate, Carbon $endDate, float $taxRate = 0.0): Invoice
    {
        $timeLogs = $project->timeLogs()
            ->whereBetween('logged_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereDoesntHave('invoiceItem')
            ->get();

        if ($timeLogs->isEmpty()) {
            throw new \RuntimeException('No unbilled time logs found for the given date range.');
        }

        $subtotal = $this->calculateSubtotal($timeLogs, $project->hourly_rate);
        $taxAmount = round($subtotal * $taxRate, 2);
        $total = $subtotal + $taxAmount;

        $invoice = Invoice::create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'invoice_number' => $this->invoiceNumberService->generate(),
            'status' => InvoiceStatus::Pending,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        foreach ($timeLogs as $timeLog) {
            $lineTotal = round((float) $timeLog->hours * (float) $project->hourly_rate, 2);

            $invoice->items()->create([
                'time_log_id' => $timeLog->id,
                'description' => $timeLog->description,
                'quantity' => $timeLog->hours,
                'unit_price' => $project->hourly_rate,
                'total' => $lineTotal,
            ]);
        }

        return $invoice->load(['client', 'project', 'items']);
    }

    /** @param Collection<int, TimeLog> $timeLogs */
    private function calculateSubtotal(Collection $timeLogs, string $hourlyRate): float
    {
        return round(
            $timeLogs->sum(fn ($log) => (float) $log->hours * (float) $hourlyRate),
            2,
        );
    }
}
