<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.crm.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'quote_date' => 'sometimes|date',
            'valid_until' => 'sometimes|nullable|date|after_or_equal:quote_date',
            'due_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:5000',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            // Optional override — service falls back to product/variant price.
            'items.*.unit_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.due_date' => 'sometimes|nullable|date',
            'items.*.notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
