<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomElement([0.5, 1, 1.5, 2, 3, 4]);
        $unitPrice = fake()->randomElement([50, 65, 75, 85, 100]);
        $total = round($quantity * $unitPrice, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'time_log_id' => null,
            'description' => fake()->sentence(6),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
        ];
    }
}
