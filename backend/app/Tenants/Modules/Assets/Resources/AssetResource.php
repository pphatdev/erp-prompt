<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'assetCode'                => $this->asset_code,
            'serialNumber'             => $this->serial_number,
            'name'                     => $this->name,
            'description'              => $this->description,
            'category'                 => $this->category,
            'vendorName'               => $this->vendor_name,
            'purchaseDate'             => $this->purchase_date,
            'purchasePrice'            => (float) $this->purchase_price,
            'salvageValue'             => (float) $this->salvage_value,
            'accumulatedDepreciation'  => (float) $this->accumulated_depreciation,
            'netBookValue'             => (float) $this->net_book_value,
            'usefulLifeMonths'         => $this->useful_life_months,
            'depreciationMethod'       => $this->depreciation_method,
            'status'                   => $this->status,
            'condition'                => $this->condition,
            'qrCodeUrl'                => $this->qr_code_url,
            'notes'                    => $this->notes,
            'custodianEmployeeId'      => $this->custodian_employee_id,
            'locationId'               => $this->location_id,
            // Closure form of whenLoaded — calling Resource::collection() on the
            // MissingValue placeholder crashes inside Laravel's ResourceCollection
            // (it tries to map() over a null $collection). Only emit these keys
            // when the relations were actually eager-loaded (show()).
            'depreciationLogs'         => $this->whenLoaded('depreciationLogs', fn () => DepreciationLogResource::collection($this->depreciationLogs)),
            'revaluations'             => $this->whenLoaded('revaluations',     fn () => AssetRevaluationResource::collection($this->revaluations)),
            'disposals'                => $this->whenLoaded('disposals',        fn () => AssetDisposalResource::collection($this->disposals)),
            'createdAt'                => $this->created_at,
            'updatedAt'                => $this->updated_at,
        ];
    }
}
