<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function database()
    {
        if (app()->isProduction()) {
            return back()->with('error', 'Backup lokal dinonaktifkan di production. Gunakan fitur automated backup MySQL Aiven.');
        }

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
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur backup lokal dinonaktifkan di production.');
        }

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
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur backup lokal dinonaktifkan di production.');
        }

        $path = storage_path("app/backups/{$filename}");

        if (! File::exists($path)) {
            return back()->with('error', 'File backup tidak ditemukan.');
        }

        ActivityLog::log('Download Backup', 'Mengunduh file backup: '.$filename);

        return response()->download($path);
    }

    public function destroy(string $filename)
    {
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur backup lokal dinonaktifkan di production.');
        }

        $path = storage_path("app/backups/{$filename}");

        if (File::exists($path)) {
            File::delete($path);
        }

        ActivityLog::log('Hapus Backup', 'Menghapus file backup: '.$filename);

        return back()->with('success', 'Backup berhasil dihapus.');
    }

    public function restoreForm()
    {
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur restore lokal dinonaktifkan di production.');
        }

        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                $backups[] = [
                    'name' => $file->getFilename(),
                    'size' => round($file->getSize() / 1048576, 2),
                    'date' => date('d/m/Y H:i', $file->getMTime()),
                ];
            }
            rsort($backups);
        }

        return view('backups.restore', compact('backups'));
    }

    public function restore(Request $request)
    {
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur restore lokal dinonaktifkan di production.');
        }

        $request->validate([
            'backup_file' => 'required|string',
        ], [
            'backup_file.required' => 'Pilih file backup yang akan di-restore.',
        ]);

        $filename = $request->input('backup_file');
        $path = storage_path("app/backups/{$filename}");

        if (! File::exists($path)) {
            return back()->with('error', 'File backup tidak ditemukan.');
        }

        $dbPath = database_path('database.sqlite');

        File::copy($dbPath, storage_path('app/backups/pre-restore-'.now()->format('Ymd-His').'.sqlite'));
        File::copy($path, $dbPath);

        ActivityLog::log('Restore Database', "Restore database dari: {$filename}");

        return back()->with('success', "Database berhasil di-restore dari {$filename}. Re-login mungkin diperlukan.");
    }

    public function upload(Request $request)
    {
        if (app()->isProduction()) {
            return back()->with('error', 'Fitur upload backup dinonaktifkan di production.');
        }

        $request->validate([
            'backup_upload' => 'required|file|mimes:sqlite,sql,db',
        ], [
            'backup_upload.required' => 'Pilih file backup untuk di-upload.',
            'backup_upload.mimes' => 'Format file harus .sqlite, .sql, atau .db.',
        ]);

        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'upload-'.now()->format('Ymd-His').'.'.$request->file('backup_upload')->getClientOriginalExtension();
        $request->file('backup_upload')->move($backupDir, $filename);

        ActivityLog::log('Upload Backup', "Upload file backup: {$filename}");

        return back()->with('success', "File backup {$filename} berhasil di-upload.");
    }
}
