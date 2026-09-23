<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function store(StorePlanRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => Plan::create($request->validated()),
        ], 201);
    }
}
