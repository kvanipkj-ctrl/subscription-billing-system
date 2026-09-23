<?php

namespace App\Http\Controllers;

use App\Exceptions\UsageIdempotencyConflict;
use App\Http\Requests\StoreUsageRequest;
use App\Services\UsageIngestionService;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function __construct(private readonly UsageIngestionService $usage)
    {
    }

    public function store(StoreUsageRequest $request): JsonResponse
    {
        try {
            $result = $this->usage->ingest($request->validated());
        } catch (UsageIdempotencyConflict $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $result['event'],
            'replayed' => ! $result['created'],
        ], $result['created'] ? 201 : 200);
    }
}
