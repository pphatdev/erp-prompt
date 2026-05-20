<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // workflow_statuses (module = hrm.application) is the authoritative
        // enum — WorkflowStatusService::validateTransition() enforces it at
        // the service layer. Keeping a static `in:` here would forbid tenants
        // adding statuses like `assessment` / `assessment_completed`.
        return [
            'status' => 'required|string|max:40',
        ];
    }
}
