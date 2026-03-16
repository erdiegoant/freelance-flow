<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Http\Requests\InvoiceCallbackRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceBuilderService;
use App\Services\InvoiceCallbackService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function store(
        GenerateInvoiceRequest $request,
        Project $project,
        InvoiceBuilderService $invoiceBuilder,
    ): JsonResponse {
        try {
            $invoice = $invoiceBuilder->build(
                project: $project,
                startDate: Carbon::parse($request->validated('start_date')),
                endDate: Carbon::parse($request->validated('end_date')),
                taxRate: (float) $request->validated('tax_rate', 0),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $invoice->update(['status' => InvoiceStatus::Processing]);

        GenerateInvoicePdf::dispatch($invoice);

        return response()->json(new InvoiceResource($invoice), 202);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['client', 'project', 'items']);

        return response()->json(new InvoiceResource($invoice));
    }

    public function callback(
        InvoiceCallbackRequest $request,
        Invoice $invoice,
        InvoiceCallbackService $callbackService,
    ): JsonResponse {
        $callbackService->handle(
            invoice: $invoice,
            status: $request->validated('status'),
            pdfPath: $request->validated('pdf_path'),
            error: $request->validated('error'),
        );

        return response()->json(['message' => 'Callback processed.']);
    }
}
