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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $invoices = $project->invoices()->latest()->paginate();

        return InvoiceResource::collection($invoices);
    }

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

        new GenerateInvoicePdf($invoice)->handle();

        return new InvoiceResource($invoice)->response()->setStatusCode(202);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return (new InvoiceResource($invoice))->response();
    }

    public function download(Invoice $invoice): StreamedResponse|Response
    {
        abort_if(! $invoice->pdf_path || ! Storage::disk('s3')->exists($invoice->pdf_path), 404);

        return Storage::disk('s3')->download(
            $invoice->pdf_path,
            "{$invoice->invoice_number}.pdf",
        );
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
