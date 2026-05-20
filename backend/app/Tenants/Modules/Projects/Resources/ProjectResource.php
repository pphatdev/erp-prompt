<?php

namespace App\Tenants\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'budget' => (float) $this->budget,
            'status' => $this->status,
            'manager_id' => $this->manager_id,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
