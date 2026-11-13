<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Backup::class);

        $backups = Backup::query()->with('user')->latest('id')->limit(50)->get();

        return response()->json([
            'data' => $backups->map(fn (Backup $backup) => $this->present($backup)),
            'meta' => [
                'total_size' => (int) $backups->sum('size'),
                'free_space' => $this->diskFreeSpace(),
                'kinds' => Backup::KINDS,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Backup::class);

        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', Backup::KINDS)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $backup = $this->backups->create(
                $request->user(),
                $validated['kind'],
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The backup could not be created: '.$exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Backup created.',
            'data' => $this->present($backup->load('user')),
        ], 201);
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        Gate::authorize('restore', $backup);

        try {
            $result = $this->backups->restore($backup, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The backup could not be restored: '.$exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Backup restored. A safety snapshot of the previous state was saved.',
            'data' => $this->present($result['restored']->load('user')),
            'meta' => ['safety_backup' => $this->present($result['safety']->load('user'))],
        ]);
    }

    public function download(Backup $backup): StreamedResponse
    {
        Gate::authorize('view', $backup);

        abort_unless(
            Storage::disk($backup->disk)->exists($backup->path),
            404,
            'The backup file is no longer available.',
        );

        return $this->backups->download($backup);
    }

    public function destroy(Backup $backup): JsonResponse
    {
        Gate::authorize('delete', $backup);

        $this->backups->delete($backup);

        return response()->json(['message' => 'Backup deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Backup $backup): array
    {
        /** @var User|null $user */
        $user = $backup->relationLoaded('user') ? $backup->user : null;

        return [
            'id' => $backup->getKey(),
            'kind' => $backup->kind,
            'filename' => $backup->filename,
            'size' => (int) $backup->size,
            'checksum' => $backup->checksum,
            'note' => $backup->note,
            'created_at' => $backup->created_at?->toIso8601String(),
            'author' => $user?->only(['id', 'name', 'display_name']),
        ];
    }

    private function diskFreeSpace(): ?int
    {
        $path = storage_path('app');

        $free = @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }
}
