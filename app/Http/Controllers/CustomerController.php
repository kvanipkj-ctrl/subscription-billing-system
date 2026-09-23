<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerUsageRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => Customer::create($request->validated()),
        ], 201);
    }

    public function usage(CustomerUsageRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();
        abort_unless((int) $customer->merchant_id === (int) $validated['merchant_id'], 404);

        $query = DB::table('daily_usage')
            ->where('merchant_id', $customer->merchant_id)
            ->where('customer_id', $customer->id)
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('usage_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('usage_date', '<=', $date))
            ->when($validated['subscription_id'] ?? null, fn ($query, $id) => $query->where('subscription_id', $id))
            ->select('usage_date', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('usage_date')
            ->orderBy('usage_date');

        return response()->json([
            'status' => 'ok',
            'data' => [
                'customer_id' => $customer->id,
                'merchant_id' => $customer->merchant_id,
                'usage' => $query->get(),
            ],
        ]);
    }
}
