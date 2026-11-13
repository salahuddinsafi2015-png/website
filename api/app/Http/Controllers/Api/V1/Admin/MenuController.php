<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Menu::class);

        return MenuResource::collection(
            Menu::query()
                ->withCount('items')
                ->orderBy('name')
                ->get()
        )->additional([
            // The dashboard renders location choices from this registry, so the
            // allowed values never drift between config and UI.
            'meta' => ['locations' => (object) config('cms.menu_locations', [])],
        ]);
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        Gate::authorize('create', Menu::class);

        $menu = Menu::create($request->validated());

        return response()->json([
            'message' => 'Menu created.',
            'data' => new MenuResource($menu->load('items')),
        ], Response::HTTP_CREATED);
    }

    public function show(Menu $menu): MenuResource
    {
        Gate::authorize('view', $menu);

        return new MenuResource(
            $menu->load(['items.linkable'])
        );
    }

    public function update(StoreMenuRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $menu->fill($request->validated())->save();

        return response()->json([
            'message' => 'Menu updated.',
            'data' => new MenuResource($menu->refresh()->load('items.linkable')),
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        Gate::authorize('delete', $menu);

        // Menu items cascade with the menu.
        $menu->delete();

        return response()->json(['message' => 'Menu deleted.']);
    }

    public function storeItem(StoreMenuItemRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $data = $request->validated();
        $this->guardParent($data['parent_id'] ?? null, $menu);

        $item = $menu->items()->create([
            ...$data,
            'linkable_type' => $request->linkableType(),
            'linkable_id' => $data['linkable_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Menu item added.',
            'data' => new MenuItemResource($item->load('linkable')),
        ], Response::HTTP_CREATED);
    }

    public function updateItem(StoreMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        Gate::authorize('update', $menuItem->menu);

        $data = $request->validated();
        $this->guardParent($data['parent_id'] ?? $menuItem->parent_id, $menuItem->menu, $menuItem);

        $menuItem->fill([
            ...$data,
            'linkable_type' => $request->has('type') ? $request->linkableType() : $menuItem->linkable_type,
            'linkable_id' => array_key_exists('linkable_id', $data) ? $data['linkable_id'] : $menuItem->linkable_id,
        ])->save();

        return response()->json([
            'message' => 'Menu item updated.',
            'data' => new MenuItemResource($menuItem->refresh()->load('linkable')),
        ]);
    }

    public function destroyItem(MenuItem $menuItem): JsonResponse
    {
        Gate::authorize('update', $menuItem->menu);

        // Children are promoted rather than deleted with the parent.
        $menuItem->children()->update(['parent_id' => null]);
        $menuItem->delete();

        return response()->json(['message' => 'Menu item removed.']);
    }

    /**
     * Persist a drag-and-drop ordering for every item the editor moved.
     *
     * The dashboard sends only the affected sibling group, so the payload is
     * validated per entry instead of against a full-tree snapshot.
     */
    public function reorderItems(Request $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $items = MenuItem::query()
            ->whereIn('id', array_column($validated['items'], 'id'))
            ->where('menu_id', $menu->getKey())
            ->get()
            ->keyBy(fn (MenuItem $item): int => (int) $item->getKey());

        foreach ($validated['items'] as $entry) {
            abort_if(
                ! $items->has((int) $entry['id']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'A menu item belongs to a different menu.',
            );

            $parentId = $entry['parent_id'] ?? null;

            if ($parentId !== null) {
                abort_unless(
                    MenuItem::query()
                        ->whereKey($parentId)
                        ->where('menu_id', $menu->getKey())
                        ->whereKeyNot((int) $entry['id'])
                        ->exists(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'The parent item belongs to a different menu.',
                );

                if ($this->wouldCreateCycle($items[(int) $entry['id']], $parentId)) {
                    abort(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        'That nesting would make the item a descendant of itself.',
                    );
                }
            }
        }

        DB::transaction(function () use ($validated, $items): void {
            foreach ($validated['items'] as $entry) {
                $item = $items[(int) $entry['id']];
                $item->parent_id = $entry['parent_id'] ?? null;
                $item->sort_order = $entry['sort_order'];
                $item->save();
            }
        });

        return response()->json([
            'message' => 'Menu order saved.',
            'data' => new MenuResource($menu->load('items.linkable')),
        ]);
    }

    protected function guardParent(?int $parentId, Menu $menu, ?MenuItem $item = null): void
    {
        if ($parentId === null) {
            return;
        }

        abort_if(
            $item !== null && $parentId === $item->getKey(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'A menu item cannot be its own parent.',
        );

        abort_unless(
            MenuItem::query()->whereKey($parentId)->where('menu_id', $menu->getKey())->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'The parent item belongs to a different menu.',
        );

        if ($item !== null && $this->wouldCreateCycle($item, $parentId)) {
            abort(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'That nesting would make the item a descendant of itself.',
            );
        }
    }

    /**
     * A move is valid unless the new parent chain already contains the item.
     */
    protected function wouldCreateCycle(MenuItem $item, int $parentId): bool
    {
        $cursor = $parentId;
        $depth = 0;

        while ($cursor !== null && $depth < 32) {
            if ((int) $cursor === (int) $item->getKey()) {
                return true;
            }

            $cursor = MenuItem::query()
                ->whereKey($cursor)
                ->value('parent_id');
            $depth++;
        }

        return false;
    }
}
