<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Enums\TermTaxonomy;
use App\Http\Controllers\Controller;
use App\Http\Resources\TermResource;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'taxonomy' => ['sometimes', 'string', Rule::in(array_keys((array) config('cms.taxonomies', [])))],
        ]);

        $terms = Term::query()
            ->taxonomy($validated['taxonomy'] ?? TermTaxonomy::Category->value)
            ->with('children')
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get();

        return TermResource::collection($terms);
    }

    public function show(string $taxonomy, string $slug): TermResource
    {
        $term = Term::query()
            ->taxonomy($taxonomy)
            ->where('slug', $slug)
            ->with(['children'])
            ->firstOrFail();

        return new TermResource($term);
    }
}
