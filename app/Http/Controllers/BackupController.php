<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function status()
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            // Fail open: an unreachable DB should never block the UI.
            return response()->json(['needed' => false, 'reason' => 'db_unreachable']);
        }

        return response()->json(['needed' => !DatabaseBackupService::hasTodaysBackup()]);
    }

    public function run(DatabaseBackupService $service)
    {
        try {
            $path = $service->run();

            return response()->json(['success' => true, 'path' => basename($path)]);
        } catch (\Exception $e) {
            Log::error('Database backup failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Backup failed. Check the application log.'], 500);
        }
    }
}
