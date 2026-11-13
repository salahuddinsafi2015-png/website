<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Only settings explicitly marked public are exposed.
     */
    public function index(): JsonResponse
    {
        $settings = array_merge([
            'site.name' => config('cms.site.name'),
            'site.description' => config('cms.site.description'),
            'site.posts_per_page' => (int) config('cms.site.posts_per_page'),
        ], Setting::publicValues());

        return response()->json(['data' => $settings]);
    }
}
