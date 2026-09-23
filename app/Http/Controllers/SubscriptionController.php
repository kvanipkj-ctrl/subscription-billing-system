<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $this->subscriptions->create($request->validated()),
        ], 201);
    }
}
