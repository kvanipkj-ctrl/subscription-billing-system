<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $subscription = \App\Models\Subscription::query()
            ->whereKey($validated['subscription_id'])
            ->where('merchant_id', $validated['merchant_id'])
            ->firstOrFail();

        $result = $this->invoices->generate(
            (int) $validated['merchant_id'],
            $subscription,
            CarbonImmutable::parse($validated['billing_period_start']),
            CarbonImmutable::parse($validated['billing_period_end'])
        );

        return response()->json([
            'status' => 'ok',
            'data' => $result['invoice'],
            'replayed' => ! $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $merchantId = $request->validate(['merchant_id' => ['required', 'integer']])['merchant_id'];
        abort_unless((int) $invoice->merchant_id === (int) $merchantId, 404);

        return response()->json([
            'status' => 'ok',
            'data' => $invoice->load(['customer', 'subscription.plan', 'invoiceLines']),
        ]);
    }
}
