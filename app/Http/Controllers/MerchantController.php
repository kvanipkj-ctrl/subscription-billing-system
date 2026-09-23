<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMerchantRequest;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;

class MerchantController extends Controller
{
    public function store(StoreMerchantRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => Merchant::create($request->validated()),
        ], 201);
    }
}
