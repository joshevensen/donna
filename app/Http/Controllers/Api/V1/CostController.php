<?php

namespace App\Http\Controllers\Api\V1;

use App\Costs\CostReport;
use App\Enums\CostPeriod;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CostController extends Controller
{
    /**
     * What Donna has cost today and this month, by purpose, for `/cost`.
     */
    public function index(CostReport $costs): JsonResponse
    {
        return response()->json(['data' => [
            'today' => $costs->for(CostPeriod::Today),
            'this_month' => $costs->for(CostPeriod::ThisMonth),
        ]]);
    }
}
