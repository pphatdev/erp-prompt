<?php

declare(strict_types=1);

namespace App\Tenants\Modules\EDocuments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => 'nullable|uuid|exists:folders,id',
        ];
    }
}
