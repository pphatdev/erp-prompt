<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|uuid|exists:folders,id',
        ];
    }
}
