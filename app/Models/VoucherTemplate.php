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
            $path = $hotspotDir.DIRECTORY_SEPARATOR.$filename;
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
