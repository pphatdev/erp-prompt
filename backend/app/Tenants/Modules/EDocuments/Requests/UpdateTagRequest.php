<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tag')?->id;

        return [
            'name' => [
                'sometimes', 'string', 'max:80',
                Rule::unique('tags', 'name')->ignore($id)->whereNull('deleted_at'),
            ],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:80',
                Rule::unique('tags', 'slug')->ignore($id)->whereNull('deleted_at'),
            ],
        ];
    }
}
