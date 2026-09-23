<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\MerchantDashboardService;
use Illuminate\View\View;

class MerchantDashboardController extends Controller
{
    public function __construct(private readonly MerchantDashboardService $dashboard)
    {
    }

    public function show(Merchant $merchant): View
    {
        return view('merchants.dashboard', $this->dashboard->forMerchant($merchant));
    }
}
