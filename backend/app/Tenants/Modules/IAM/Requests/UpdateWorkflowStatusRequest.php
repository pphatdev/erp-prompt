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

    /**
     * Coerce null / empty color to 'secondary' before validation. The
     * `workflow_statuses.color` column is NOT NULL with a DB default that
     * only kicks in when the column is omitted from the INSERT — explicit
     * NULL trips 23502. See StoreWorkflowStatusRequest for the same fix.
     * Only applied when the caller actually sent the field (PATCH-friendly).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('color')) {
            $raw = $this->input('color');
            if ($raw === null || $raw === '') {
                $this->merge(['color' => 'secondary']);
            }
        }
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
