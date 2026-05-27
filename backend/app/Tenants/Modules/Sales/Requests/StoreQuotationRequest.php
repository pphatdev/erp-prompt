<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.quotations.write')
            || $this->user()?->can('sales.crm.write')
            || $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Either customer_id OR from_opportunity_id must be set. Validated in withValidator.
            'customer_id'         => 'sometimes|nullable|uuid|exists:customers,id',
            'from_opportunity_id' => 'sometimes|nullable|uuid|exists:opportunities,id',

            'quote_date'  => 'sometimes|date',
            'valid_until' => 'sometimes|nullable|date|after_or_equal:quote_date',
            'due_date'    => 'sometimes|nullable|date',
            'notes'       => 'sometimes|nullable|string|max:5000',

            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.due_date'   => 'sometimes|nullable|date',
            'items.*.notes'      => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (empty($this->input('customer_id')) && empty($this->input('from_opportunity_id'))) {
                $v->errors()->add('customer_id', 'Either customer_id or from_opportunity_id is required.');
            }
        });
    }
}
