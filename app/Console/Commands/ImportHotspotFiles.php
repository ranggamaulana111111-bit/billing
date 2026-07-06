<?php

namespace App\Console\Commands;

use App\Models\VoucherTemplate;
use Illuminate\Console\Command;

class ImportHotspotFiles extends Command
{
    protected $signature = 'hotspot:import
        {--name=Default : Nama template}
        {--dir= : Path folder template (default: public/hotspot/)}';

    protected $description = 'Import folder hotspot template ke database';

    public function handle(): int
    {
        $sourceDir = $this->option('dir') ?: public_path('hotspot');
        $name = $this->option('name');

        if (! is_dir($sourceDir)) {
            $this->error("Directory {$sourceDir} tidak ditemukan.");

            return Command::FAILURE;
        }

        $template = VoucherTemplate::create([
            'name' => $name,
            'is_active' => true,
        ]);

        $destDir = $template->templatePath();

        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $this->copyDirectory($sourceDir, $destDir);

        if ($this->hasRequiredFiles($destDir)) {
            $this->info('Folder berisi file hotspot yang valid (login.html ditemukan).');
        } else {
            $this->warn('Peringatan: login.html tidak ditemukan di folder sumber. Template mungkin tidak berfungsi.');
        }

        $files = $this->countFiles($destDir);
        $this->info("Template '{$template->name}' berhasil dibuat dari {$files} file.");

        if ($template->is_active) {
            $template->syncToActive();
            $this->info('Template aktif disalin ke folder hotspot.');
        }

        return Command::SUCCESS;
    }

    private function hasRequiredFiles(string $dir): bool
    {
        return file_exists($dir . DIRECTORY_SEPARATOR . 'login.html');
    }

    private function countFiles(string $dir): int
    {
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! $dir) {
            $this->error("Gagal membaca folder sumber: {$src}");

            return;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $src . DIRECTORY_SEPARATOR . $file;
            $dstFile = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcFile)) {
                if (! is_dir($dstFile)) {
                    mkdir($dstFile, 0755, true);
                }
                $this->copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }

        closedir($dir);
    }
}
