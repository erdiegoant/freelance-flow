<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceCallbackRequest extends FormRequest
{
    /**
     * Authorize by verifying the shared secret header sent by the Go worker.
     */
    public function authorize(): bool
    {
        $secret = config('services.invoice_worker.callback_secret');

        return $this->header('X-Callback-Secret') === $secret;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:completed,failed'],
            'pdf_path' => ['required_if:status,completed', 'nullable', 'string'],
            'error' => ['nullable', 'string'],
        ];
    }
}
