<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'date'        => optional($this->date)->toDateString(),
            'type'        => $this->type,
            'isRecurring' => (bool) $this->is_recurring,
            'notes'       => $this->notes,
            'createdAt'   => optional($this->created_at)->toIso8601String(),
            'updatedAt'   => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
