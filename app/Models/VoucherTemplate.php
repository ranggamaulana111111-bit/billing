<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class VoucherTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'content', 'status_page', 'redirect_page', 'error_page', 'alive_page', 'logout_page', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getPage(string $type): ?string
    {
        return match ($type) {
            'login' => $this->content,
            'status' => $this->status_page,
            'redirect' => $this->redirect_page,
            'error' => $this->error_page,
            'alive' => $this->alive_page,
            'logout' => $this->logout_page,
            default => null,
        };
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'voucher_template_id');
    }

    public function templatePath(): string
    {
        return public_path('hotspot/templates/' . $this->id);
    }

    public function hasFiles(): bool
    {
        return is_dir($this->templatePath());
    }

    public function fileUrl(string $path = ''): string
    {
        $base = url('hotspot/templates/' . $this->id);
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }

    public function syncToActive(): void
    {
        if (! $this->is_active || ! $this->hasFiles()) {
            return;
        }

        $src = $this->templatePath();
        $dst = public_path('hotspot');

        if (! is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $this->copyDirectory($src, $dst);
    }

    public function removeTemplateDirectory(): void
    {
        $path = $this->templatePath();
        if (is_dir($path)) {
            $this->deleteDirectory($path);
        }
    }

    public function isFileBased(): bool
    {
        return $this->hasFiles();
    }

    private function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! $dir) {
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
                @copy($srcFile, $dstFile);
            }
        }

        closedir($dir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }

    // writeFiles() tidak dipanggil otomatis — Vercel readonly filesystem
    // File hotspot disajikan langsung dari database via route dinamis

    public function writeFiles(): void
    {
        $map = [
            'content' => 'login.html',
            'status_page' => 'status.html',
            'redirect_page' => 'redirect.html',
            'error_page' => 'error.html',
            'alive_page' => 'alive.html',
            'logout_page' => 'logout.html',
        ];

        $hotspotDir = public_path('hotspot');

        if (! is_dir($hotspotDir)) {
            mkdir($hotspotDir, 0755, true);
        }

        foreach ($map as $attribute => $filename) {
            $path = $hotspotDir . DIRECTORY_SEPARATOR . $filename;
            $content = $this->{$attribute};

            if (is_null($content) || trim($content) === '') {
                if (file_exists($path)) {
                    unlink($path);
                }

                continue;
            }

            $written = @file_put_contents($path, $content);

            if ($written === false) {
                Log::error("Gagal menulis file hotspot: {$path}");
            }
        }
    }
}
