<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderWorkflowStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('iam.workflow_statuses.write') ?? true;
    }

    public function rules(): array
    {
        return [
            'module'         => 'required|string|max:60',
            'orderedKeys'    => 'required|array|min:1',
            'orderedKeys.*'  => 'required|string|max:40|regex:/^[a-z0-9_]+$/',
        ];
    }
}
