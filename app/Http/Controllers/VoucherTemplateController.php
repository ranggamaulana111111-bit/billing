<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\VoucherTemplate;
use Illuminate\Http\Request;
use ZipArchive;

class VoucherTemplateController extends Controller
{
    public function index()
    {
        $templates = VoucherTemplate::orderBy('name')->get();

        return view('voucher-templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template_file' => 'required|file|mimes:zip|max:10240',
        ]);

        $template = VoucherTemplate::create([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        $this->extractZip($request->file('template_file'), $template);

        ActivityLog::log('Tambah Template', 'Menambahkan template landing page: '.$template->name);

        return back()->with('success', 'Template "'.$template->name.'" berhasil ditambahkan.');
    }

    public function update(Request $request, VoucherTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template_file' => 'nullable|file|mimes:zip|max:10240',
        ]);

        $template->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        if ($request->hasFile('template_file')) {
            $template->removeTemplateDirectory();
            $this->extractZip($request->file('template_file'), $template);
        }

        if ($template->is_active && $template->hasFiles()) {
            $template->syncToActive();
        }

        ActivityLog::log('Ubah Template', 'Mengubah template landing page: '.$template->name);

        return back()->with('success', 'Template "'.$template->name.'" berhasil diperbarui.');
    }

    public function destroy(VoucherTemplate $template)
    {
        $name = $template->name;
        $template->removeTemplateDirectory();
        $template->delete();

        ActivityLog::log('Hapus Template', 'Menghapus template landing page: '.$name);

        return back()->with('success', 'Template "'.$name.'" berhasil dihapus.');
    }

    public function preview(VoucherTemplate $template, ?string $page = null)
    {
        $company = Setting::get('company_name', 'ALKONEKbill');
        $page = $page ?: request('page', 'login');

        $content = $template->getPage($page);

        return view('voucher-templates.preview', compact('template', 'company', 'page', 'content'));
    }

    public function serveFile(VoucherTemplate $template, string $path)
    {
        $filePath = $template->templatePath().DIRECTORY_SEPARATOR.ltrim($path, '/\\');

        if (str_contains($path, '..') || ! file_exists($filePath) || is_dir($filePath)) {
            abort(404);
        }

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        return response()->file($filePath, ['Content-Type' => $mime]);
    }

    private function extractZip($file, VoucherTemplate $template): void
    {
        $zip = new ZipArchive;
        $res = $zip->open($file->getRealPath());

        if ($res !== true) {
            throw new \RuntimeException('Gagal membuka file ZIP');
        }

        $extractPath = $template->templatePath();

        if (is_dir($extractPath)) {
            $template->removeTemplateDirectory();
        }

        mkdir($extractPath, 0755, true);

        $zip->extractTo($extractPath);
        $zip->close();

        $this->flattenSingleSubdirectory($extractPath);

        if ($template->is_active) {
            $template->syncToActive();
        }
    }

    private function flattenSingleSubdirectory(string $path): void
    {
        $items = array_diff(scandir($path), ['.', '..']);

        if (count($items) === 1) {
            $only = reset($items);
            $sub = $path.DIRECTORY_SEPARATOR.$only;

            if (is_dir($sub)) {
                $subItems = array_diff(scandir($sub), ['.', '..']);

                foreach ($subItems as $item) {
                    $src = $sub.DIRECTORY_SEPARATOR.$item;
                    $dst = $path.DIRECTORY_SEPARATOR.$item;

                    rename($src, $dst);
                }

                @rmdir($sub);
            }
        }
    }
}
