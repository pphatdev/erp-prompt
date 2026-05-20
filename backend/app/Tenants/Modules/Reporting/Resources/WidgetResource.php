<?php

namespace App\Tenants\Modules\Reporting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dashboard_id' => $this->dashboard_id,
            'type' => $this->type,
            'data_source' => $this->data_source,
            'config' => $this->config,
            'data' => $this->when(isset($this->data), $this->data), // Dynamic data fetched via service
        ];
    }
}
