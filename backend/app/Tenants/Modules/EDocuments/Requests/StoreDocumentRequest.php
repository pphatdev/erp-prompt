<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240',
            'title' => 'nullable|string|max:255',
            'folder_id' => 'nullable|uuid|exists:folders,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'uuid|exists:tags,id',
            'documentable_type' => 'nullable|string|max:255',
            'documentable_id' => 'nullable|uuid',
        ];
    }
}
