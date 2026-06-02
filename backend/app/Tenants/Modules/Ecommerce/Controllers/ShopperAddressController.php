<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomAddress;
use App\Tenants\Modules\Ecommerce\Resources\EcomAddressResource;
use App\Tenants\Modules\Ecommerce\Services\EcomCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ShopperAddressController extends Controller
{
    public function __construct(private readonly EcomCustomerService $customers)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = Auth::guard('shop')->user();
        return EcomAddressResource::collection($customer->addresses);
    }

    public function store(Request $request): EcomAddressResource
    {
        $data = $request->validate($this->rules());
        $customer = Auth::guard('shop')->user();

        $address = $this->customers->addAddress($customer, $data);

        return new EcomAddressResource($address);
    }

    public function update(Request $request, EcomAddress $address): EcomAddressResource|JsonResponse
    {
        $customer = Auth::guard('shop')->user();
        if ($address->customer_id !== $customer->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate($this->rules(partial: true));

        $address = $this->customers->updateAddress($address, $data);

        return new EcomAddressResource($address);
    }

    public function destroy(Request $request, EcomAddress $address): JsonResponse
    {
        $customer = Auth::guard('shop')->user();
        if ($address->customer_id !== $customer->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->customers->deleteAddress($address);

        return response()->json(['message' => 'Address removed.']);
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        return [
            'label' => 'sometimes|nullable|string|max:60',
            'recipient_name' => "$req|string|max:120",
            'phone' => 'sometimes|nullable|string|max:40',
            'line1' => "$req|string|max:255",
            'line2' => 'sometimes|nullable|string|max:255',
            'city' => "$req|string|max:120",
            'state' => 'sometimes|nullable|string|max:120',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => "$req|string|size:2",
            'is_default_shipping' => 'sometimes|boolean',
            'is_default_billing' => 'sometimes|boolean',
        ];
    }
}
