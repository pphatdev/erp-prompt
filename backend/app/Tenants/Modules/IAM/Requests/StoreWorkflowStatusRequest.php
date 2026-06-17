<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Requests;

use App\Models\Tenant\WorkflowStatus;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('iam.workflow_statuses.write') ?? true;
    }

    /**
     * The `workflow_statuses.color` column is NOT NULL with a DB default
     * of 'secondary'. Postgres only applies that default when the column
     * is OMITTED from the INSERT — explicit NULL bypasses it and trips
     * 23502. Coerce empty / null color to 'secondary' here so the
     * controller never persists a NULL.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('color');
        if ($raw === null || $raw === '') {
            $this->merge(['color' => 'secondary']);
        }

        // Auto-slug the key from the label when the Pipeline Settings UI
        // creates a candidate stage without exposing a separate key field.
        if (!$this->filled('key') && $this->filled('label') && $this->filled('module')) {
            $key = app(WorkflowStatusService::class)
                ->slugFromLabel((string) $this->input('label'), (string) $this->input('module'));
            $this->merge(['key' => $key]);
        }
    }

    public function rules(): array
    {
        return [
            'module'        => 'required|string|max:60',
            'key'           => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('workflow_statuses')->where(fn ($q) => $q->where('module', $this->input('module')))
            ],
            'label'         => 'required|string|max:80',
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
