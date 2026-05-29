<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('tags', 'name')->whereNull('deleted_at'),
            ],
            'slug' => [
                'nullable', 'string', 'max:80',
                Rule::unique('tags', 'slug')->whereNull('deleted_at'),
            ],
        ];
    }
}
