<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Liveness/readiness probe for uptime monitors and load balancers.
 *
 * Reports only pass/fail facts — never configuration values or credentials.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
                'time' => now()->toIso8601String(),
            ],
            $healthy ? 200 : 503,
        )->header('Cache-Control', 'no-store');
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $disk = Storage::disk((string) config('cms.media.disk', 'public'));
            $probe = 'health/probe-'.uniqid().'.txt';

            $disk->put($probe, 'ok');
            $ok = $disk->exists($probe) && $disk->get($probe) === 'ok';
            $disk->delete($probe);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::put('health.probe', 'ok', 10);

            return Cache::get('health.probe') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }
}
