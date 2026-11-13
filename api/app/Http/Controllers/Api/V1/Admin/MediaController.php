<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Media::class);

        $query = Media::query()->latest();

        if (! $request->user()->can('media.delete.any')) {
            $query->where('user_id', $request->user()->getKey());
        }

        $query
            ->when($request->filled('search'), fn ($q) => $q->where('filename', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('images_only'), fn ($q) => $q->where('mime_type', 'like', 'image/%'));

        return MediaResource::collection($query->paginate(24)->withQueryString());
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $media = $this->media->store($request->file('file'), $request->user(), [
            'title' => $request->validated('title'),
            'alt_text' => $request->validated('alt_text'),
            'caption' => $request->validated('caption'),
        ]);

        return response()->json([
            'message' => 'File uploaded.',
            'data' => new MediaResource($media),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        Gate::authorize('update', $media);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $media->fill($data)->save();

        return response()->json([
            'message' => 'Media updated.',
            'data' => new MediaResource($media),
        ]);
    }

    public function destroy(Media $media): JsonResponse
    {
        Gate::authorize('delete', $media);

        $this->media->delete($media);

        return response()->json(['message' => 'Media deleted.']);
    }
}
