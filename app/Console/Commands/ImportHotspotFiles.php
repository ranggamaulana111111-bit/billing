<?php

namespace App\Console\Commands;

use App\Models\VoucherTemplate;
use Illuminate\Console\Command;

class ImportHotspotFiles extends Command
{
    protected $signature = 'hotspot:import {--name=Default : Nama template}';

    protected $description = 'Import file hotspot dari public/hotspot/ ke database';

    public function handle(): int
    {
        $hotspotDir = public_path('hotspot');
        if (! is_dir($hotspotDir)) {
            $this->error('Directory public/hotspot/ tidak ditemukan.');

            return Command::FAILURE;
        }

        $map = [
            'login.html' => 'content',
            'status.html' => 'status_page',
            'redirect.html' => 'redirect_page',
            'error.html' => 'error_page',
            'alive.html' => 'alive_page',
            'logout.html' => 'logout_page',
        ];

        $data = ['name' => $this->option('name'), 'is_active' => true];

        foreach ($map as $filename => $attribute) {
            $path = $hotspotDir.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($path)) {
                $data[$attribute] = file_get_contents($path);
                $this->info("  Import {$filename}");
            }
        }

        $template = VoucherTemplate::create($data);
        $this->info("Template '{$template->name}' berhasil dibuat dari file hotspot.");

        return Command::SUCCESS;
    }
}
