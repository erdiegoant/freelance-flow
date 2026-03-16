<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 200, 5000);
        $taxRate = 0.19;
        $taxAmount = round($subtotal * $taxRate, 2);

        return [
            'client_id' => Client::factory(),
            'project_id' => Project::factory(),
            'invoice_number' => 'INV-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Pending,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'pdf_path' => null,
            'pdf_generated_at' => null,
            'due_date' => now()->addDays(30)->toDateString(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Completed,
            'pdf_path' => 'invoices/'.fake()->uuid().'.pdf',
            'pdf_generated_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => InvoiceStatus::Failed]);
    }
}
