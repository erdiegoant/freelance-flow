<?php

use App\Services\InvoiceNumberService;

describe('InvoiceNumberService', function () {
    it('generates an invoice number in the correct format', function () {
        $number = app(InvoiceNumberService::class)->generate();

        expect($number)->toMatch('/^INV-\d{4}-\d{4}$/');
    });

    it('includes the current year in the invoice number', function () {
        $number = app(InvoiceNumberService::class)->generate();

        expect($number)->toContain((string) now()->year);
    });

    it('generates sequential numbers for the same year', function () {
        $service = app(InvoiceNumberService::class);

        $first = $service->generate();
        $second = $service->generate();
        $third = $service->generate();

        expect($first)->toBe('INV-'.now()->year.'-0001')
            ->and($second)->toBe('INV-'.now()->year.'-0002')
            ->and($third)->toBe('INV-'.now()->year.'-0003');
    });

    it('zero-pads the sequence to 4 digits', function () {
        $number = app(InvoiceNumberService::class)->generate();

        $parts = explode('-', $number);
        expect(strlen($parts[2]))->toBe(4);
    });
});
