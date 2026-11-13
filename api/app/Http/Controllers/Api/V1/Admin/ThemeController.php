<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateThemeRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('theme.manage'),
            Response::HTTP_FORBIDDEN,
            'You do not have permission to manage themes.',
        );

        $themes = collect((array) config('cms.themes', []))
            ->map(fn (array $theme, string $key) => [
                'key' => $key,
                'label' => $theme['label'] ?? $key,
                'description' => $theme['description'] ?? null,
                'default_accent' => $theme['accent'] ?? null,
            ])
            ->values();

        return response()->json([
            'data' => [
                'themes' => $themes,
                'active' => (string) Setting::get('appearance.theme', 'default'),
                'accent' => (string) Setting::get('appearance.accent', config('cms.themes.default.accent')),
            ],
        ]);
    }

    public function update(UpdateThemeRequest $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('theme.manage'),
            Response::HTTP_FORBIDDEN,
            'You do not have permission to manage themes.',
        );

        $data = $request->validated();

        Setting::set('appearance.theme', $data['theme'], [
            'type' => 'string',
            'group' => 'appearance',
            'is_public' => true,
        ]);

        $accent = $data['accent'] ?? config("cms.themes.{$data['theme']}.accent");

        if (filled($accent)) {
            Setting::set('appearance.accent', $accent, [
                'type' => 'string',
                'group' => 'appearance',
                'is_public' => true,
            ]);
        }

        return response()->json([
            'message' => 'Theme updated.',
            'data' => [
                'theme' => $data['theme'],
                'accent' => $accent,
            ],
        ]);
    }
}
