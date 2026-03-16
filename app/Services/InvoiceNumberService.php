<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Generate a unique sequential invoice number in the format INV-YYYY-NNNN.
     *
     * Uses PostgreSQL's INSERT … ON CONFLICT … DO UPDATE with RETURNING to
     * atomically increment the per-year sequence, making it safe under
     * concurrent requests without application-level locking.
     */
    public function generate(): string
    {
        $year = now()->year;

        $result = DB::selectOne(
            'INSERT INTO invoice_sequences (year, last_sequence)
             VALUES (?, 1)
             ON CONFLICT (year)
             DO UPDATE SET last_sequence = invoice_sequences.last_sequence + 1
             RETURNING last_sequence',
            [$year],
        );

        return sprintf('INV-%d-%04d', $year, $result->last_sequence);
    }
}
