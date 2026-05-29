<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller-level $this->authorize() is the actual gate — see the
        // hasPermission vs can() footgun memo (Gate has no definitions for
        // our slug-based permissions, so can() resolves false). Returning
        // true here delegates to the controller.
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('vehicleModel')?->id;

        return [
            'make'      => [
                'required', 'string', 'max:80',
                // (tenant_id, make, model) is the unique tuple — Rule::unique
                // with `where` keeps the check tenant-scoped.
                Rule::unique('vehicle_models')
                    ->where(fn ($q) => $q->where('model', $this->input('model'))
                                         ->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'model'     => 'required|string|max:80',
            'body_type' => 'nullable|string|max:40',
            'fuel_type' => 'nullable|string|max:40',
            'notes'     => 'nullable|string|max:1000',
        ];
    }
}
