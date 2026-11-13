<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdSenseService;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly AdSenseService $adsense,
    ) {}

    public function analytics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('insight.view'), 403);

        $days = (int) $request->integer('days', 28);

        return response()->json(['data' => $this->analytics->overview($days)]);
    }

    public function adsense(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('insight.view'), 403);

        $days = (int) $request->integer('days', 28);

        return response()->json(['data' => $this->adsense->overview($days)]);
    }
}
