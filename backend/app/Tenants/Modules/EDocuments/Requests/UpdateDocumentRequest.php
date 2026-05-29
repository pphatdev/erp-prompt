<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'folder_id' => 'nullable|uuid|exists:folders,id',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'uuid|exists:tags,id',
            'documentable_type' => 'nullable|string|max:255',
            'documentable_id' => 'nullable|uuid',
        ];
    }
}
