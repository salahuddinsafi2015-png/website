<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWidgetRequest;
use App\Http\Resources\WidgetResource;
use App\Models\Widget;
use App\Services\ContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class WidgetController extends Controller
{
    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Widget::class);

        $validated = $request->validate([
            'area' => ['sometimes', 'string', Rule::in(array_keys((array) config('cms.widget_areas', [])))],
        ]);

        $widgets = Widget::query()
            ->when($validated['area'] ?? null, fn ($query, $area) => $query->area($area))
            ->with('menu.items')
            ->ordered()
            ->get();

        return WidgetResource::collection($widgets)->additional([
            'meta' => [
                'areas' => (object) config('cms.widget_areas', []),
                'types' => (object) config('cms.widget_types', []),
            ],
        ]);
    }

    public function store(StoreWidgetRequest $request): JsonResponse
    {
        Gate::authorize('create', Widget::class);

        $data = $request->validated();
        $widget = Widget::create($this->attributes($data, (string) $data['type']));

        return response()->json([
            'message' => 'Widget created.',
            'data' => new WidgetResource($widget->load('menu.items')),
        ], Response::HTTP_CREATED);
    }

    public function update(StoreWidgetRequest $request, Widget $widget): JsonResponse
    {
        Gate::authorize('update', $widget);

        $data = $request->validated();
        $widget->fill($this->attributes($data, (string) ($data['type'] ?? $widget->type), $widget))->save();

        return response()->json([
            'message' => 'Widget updated.',
            'data' => new WidgetResource($widget->refresh()->load('menu.items')),
        ]);
    }

    public function destroy(Widget $widget): JsonResponse
    {
        Gate::authorize('delete', $widget);

        $widget->delete();

        return response()->json(['message' => 'Widget deleted.']);
    }

    /**
     * Persist a drag-and-drop ordering for the widgets the editor moved.
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Widget::class);

        $validated = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.id' => ['required', 'integer', 'exists:widgets,id'],
            'widgets.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['widgets'] as $entry) {
                $widget = Widget::query()->findOrFail($entry['id']);
                Gate::authorize('update', $widget);
                $widget->update(['sort_order' => $entry['sort_order']]);
            }
        });

        return response()->json([
            'message' => 'Widget order saved.',
            'data' => WidgetResource::collection(
                Widget::query()->with('menu.items')->ordered()->get(),
            ),
        ]);
    }

    /**
     * Store rich text as sanitised HTML inside the JSON content column.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data, string $type, ?Widget $widget = null): array
    {
        $text = $data['content'] ?? $widget?->text();
        unset($data['content']);

        if ($type === 'rich_text') {
            $data['content'] = ['text' => $this->sanitizer->clean($text)];
        } else {
            $data['content'] = $widget?->content ?? null;
        }

        if ($type !== 'menu') {
            $data['menu_id'] = null;
        }

        return $data;
    }
}
