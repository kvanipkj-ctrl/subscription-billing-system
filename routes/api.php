<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::post('/merchants', [MerchantController::class, 'store']);
Route::post('/plans', [PlanController::class, 'store']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::post('/subscriptions', [SubscriptionController::class, 'store']);
Route::post('/usage', [UsageController::class, 'store']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
Route::get('/customers/{customer}/usage', [CustomerController::class, 'usage']);