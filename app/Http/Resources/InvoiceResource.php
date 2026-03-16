<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'due_date' => $this->due_date?->toDateString(),
            'pdf_path' => $this->pdf_path,
            'pdf_generated_at' => $this->pdf_generated_at?->toIso8601String(),
            'client' => $this->whenLoaded('client'),
            'project' => $this->whenLoaded('project'),
            'items' => $this->whenLoaded('items'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
