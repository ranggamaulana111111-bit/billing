<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function database()
    {
        $dbPath = database_path('database.sqlite');
        $backupDir = storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.sqlite';
        $destPath = "{$backupDir}/{$filename}";

        if (! File::exists($dbPath)) {
            return back()->with('error', 'Database file tidak ditemukan.');
        }

        File::copy($dbPath, $destPath);

        ActivityLog::log('Backup Database', "Backup database: {$filename}");

        return response()->download($destPath)->deleteFileAfterSend(false);
    }

    public function index()
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                $backups[] = [
                    'name' => $file->getFilename(),
                    'size' => round($file->getSize() / 1048576, 2),
                    'date' => date('d/m/Y H:i', $file->getMTime()),
                    'path' => $file->getPathname(),
                ];
            }
            rsort($backups);
        }

        return view('backups.index', compact('backups'));
    }

    public function download(string $filename)
    {
        $path = storage_path("app/backups/{$filename}");

        if (! File::exists($path)) {
            return back()->with('error', 'File backup tidak ditemukan.');
        }

        ActivityLog::log('Download Backup', 'Mengunduh file backup: '.$filename);

        return response()->download($path);
    }

    public function destroy(string $filename)
    {
        $path = storage_path("app/backups/{$filename}");

        if (File::exists($path)) {
            File::delete($path);
        }

        ActivityLog::log('Hapus Backup', 'Menghapus file backup: '.$filename);

        return back()->with('success', 'Backup berhasil dihapus.');
    }
}
