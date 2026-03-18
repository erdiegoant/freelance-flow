@component('mail::message')
# Your invoice is ready

Hi {{ $invoice->client->name }},

Invoice **{{ $invoice->invoice_number }}** for **{{ $invoice->project->name }}** has been generated and is ready for download.

| | |
|---|---|
| **Invoice number** | {{ $invoice->invoice_number }} |
| **Subtotal** | ${{ number_format($invoice->subtotal, 2) }} |
| **Tax ({{ $invoice->tax_rate * 100 }}%)** | ${{ number_format($invoice->tax_amount, 2) }} |
| **Total** | ${{ number_format($invoice->total, 2) }} |
| **Due date** | {{ $invoice->due_date->format('F j, Y') }} |

@component('mail::button', ['url' => $downloadUrl])
Download Invoice PDF
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
