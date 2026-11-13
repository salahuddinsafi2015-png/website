<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use App\Http\Resources\WidgetResource;
use App\Models\Menu;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class NavigationController extends Controller
{
    /**
     * The menu assigned to a theme location, with only active items.
     */
    public function menu(string $location): MenuResource
    {
        abort_unless(
            array_key_exists($location, (array) config('cms.menu_locations', [])),
            \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND,
            'Unknown menu location.',
        );

        $menu = Menu::query()
            ->location($location)
            ->with(['items' => fn ($query) => $query->active(), 'items.linkable'])
            ->first();

        abort_if($menu === null, \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND, 'No menu is assigned to this location.');

        return new MenuResource($menu);
    }

    public function widgets(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'area' => ['required', 'string', Rule::in(array_keys((array) config('cms.widget_areas', [])))],
        ]);

        $widgets = Widget::query()
            ->area($validated['area'])
            ->active()
            ->ordered()
            ->with(['menu.items' => fn ($query) => $query->active()])
            ->get();

        return WidgetResource::collection($widgets);
    }
}
