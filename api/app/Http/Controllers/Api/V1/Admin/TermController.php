<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Resources\TermResource;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TermController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Term::class);

        $terms = Term::query()
            ->when($request->filled('taxonomy'), fn ($q) => $q->taxonomy((string) $request->string('taxonomy')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount('posts')
            ->with('parent:id,name')
            ->orderBy('taxonomy')
            ->orderBy('menu_order')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return TermResource::collection($terms);
    }

    public function store(StoreTermRequest $request): JsonResponse
    {
        Gate::authorize('create', Term::class);

        $term = Term::create($request->validated());

        return response()->json([
            'message' => 'Term created.',
            'data' => new TermResource($term),
        ], Response::HTTP_CREATED);
    }

    public function update(StoreTermRequest $request, Term $term): JsonResponse
    {
        Gate::authorize('update', $term);

        $term->fill($request->validated())->save();
        $term->syncCount();

        return response()->json([
            'message' => 'Term updated.',
            'data' => new TermResource($term->refresh()),
        ]);
    }

    public function destroy(Term $term): JsonResponse
    {
        Gate::authorize('delete', $term);

        $term->posts()->detach();
        $term->children()->update(['parent_id' => null]);
        $term->delete();

        return response()->json(['message' => 'Term deleted.']);
    }

    /**
     * Recompute the published-post count for every term in a taxonomy.
     */
    public function recount(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Term::class);

        $taxonomy = (string) $request->string('taxonomy', 'category');

        validator(['taxonomy' => $taxonomy], [
            'taxonomy' => ['required', 'string', Rule::in(array_keys((array) config('cms.taxonomies', [])))],
        ])->validate();

        $terms = Term::query()->taxonomy($taxonomy)->get();

        $terms->each(fn (Term $term) => $term->syncCount());

        return response()->json(['message' => "Recounted {$terms->count()} terms."]);
    }
}
