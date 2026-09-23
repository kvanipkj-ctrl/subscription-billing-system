<?php

use App\Http\Controllers\MerchantDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show'])
    ->name('merchants.dashboard');

Route::get('/', function () {
    return view('welcome');
});
