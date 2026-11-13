<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        $settings = Setting::query()
            ->when($request->filled('group'), fn ($q) => $q->where('group', (string) $request->string('group')))
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
            'meta' => ['groups' => Setting::query()->distinct()->orderBy('group')->pluck('group')],
        ]);
    }

    /**
     * Bulk upsert. Keys are setting names, e.g. {"site.name": {"value": "..."}}.
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        Gate::authorize('update', Setting::class);

        /** @var array<string, array<string, mixed>> $payload */
        $payload = $request->validated('settings');

        DB::transaction(function () use ($payload): void {
            foreach ($payload as $name => $attributes) {
                Setting::set(
                    (string) $name,
                    $attributes['value'] ?? null,
                    array_filter([
                        'type' => $attributes['type'] ?? null,
                        'group' => $attributes['group'] ?? null,
                        'is_public' => $attributes['is_public'] ?? null,
                    ], fn ($value) => $value !== null),
                );
            }
        });

        return response()->json(['message' => 'Settings saved.']);
    }
}
