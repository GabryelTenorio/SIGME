<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $healthy = $this->databaseIsAvailable() && $this->privateStorageIsWritable();

        return response()
            ->json(
                ['status' => $healthy ? 'healthy' : 'unhealthy'],
                $healthy ? 200 : 503,
            )
            ->header('X-SIGME-Service', 'SIGME');
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function privateStorageIsWritable(): bool
    {
        $path = 'health/'.Str::uuid().'.tmp';

        try {
            $disk = Storage::disk('private-local');

            return $disk->put($path, 'sigme-health-check') && $disk->exists($path);
        } catch (Throwable) {
            return false;
        } finally {
            try {
                Storage::disk('private-local')->delete($path);
            } catch (Throwable) {
            }
        }
    }
}
