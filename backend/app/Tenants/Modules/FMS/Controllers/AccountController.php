<?php

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Account;
use App\Tenants\Modules\FMS\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(Account::with('children')->whereNull('parent_id')->get());
    }

    public function store(Request $request): AccountResource
    {
        $data = $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
        ]);

        $account = Account::create($data);
        return new AccountResource($account);
    }
}
