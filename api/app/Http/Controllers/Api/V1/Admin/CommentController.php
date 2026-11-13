<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Comment::class);

        $comments = Comment::query()
            ->with(['post:id,title,slug', 'user:id,name,username,avatar_url'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))
            ->when($request->filled('post_id'), fn ($q) => $q->where('post_id', $request->integer('post_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where('author_name', 'like', $search)
                    ->orWhere('author_email', 'like', $search)
                    ->orWhere('content', 'like', $search);
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return CommentResource::collection($comments);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(CommentStatus::values())],
        ]);

        $comment->forceFill(['status' => CommentStatus::from($data['status'])])->save();

        return response()->json([
            'message' => 'Comment updated.',
            'data' => new CommentResource($comment),
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
