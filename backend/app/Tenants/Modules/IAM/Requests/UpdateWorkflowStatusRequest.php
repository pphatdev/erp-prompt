<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Requests;

use App\Models\Tenant\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('iam.workflow_statuses.write') ?? true;
    }

    public function rules(): array
    {
        $id = $this->route('workflow_status')?->id;
        $module = $this->input('module', $this->route('workflow_status')?->module);

        return [
            'module'        => 'sometimes|string|max:60',
            'key'           => [
                'sometimes', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('workflow_statuses')
                    ->where(fn ($q) => $q->where('module', $module))
                    ->ignore($id)
            ],
            'label'         => 'sometimes|string|max:80',
            'color'         => ['nullable', Rule::in(WorkflowStatus::COLORS)],
            'icon'          => 'nullable|string|max:60',
            'sequence'      => 'nullable|integer|min:0',
            'is_initial'    => 'nullable|boolean',
            'is_terminal'   => 'nullable|boolean',
            'allowed_next'  => 'nullable|array',
            'allowed_next.*' => 'string|max:40',
            'metadata'      => 'nullable|array',
        ];
    }
}
