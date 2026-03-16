@component('mail::message')
# Invoice PDF generation failed

Invoice **{{ $invoice->invoice_number }}** ({{ $invoice->project->name ?? 'N/A' }}) could not be generated.

@if ($error)
**Error:** {{ $error }}
@endif

Please check the worker logs and retry the PDF generation for invoice `{{ $invoice->invoice_number }}`.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
