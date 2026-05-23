<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddQuotationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.crm.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'sometimes|nullable|numeric|min:0',
            'due_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
