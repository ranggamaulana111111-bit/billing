<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\VoucherPrintTemplate;
use Illuminate\Http\Request;

class VoucherPrintTemplateController extends Controller
{
    public function index()
    {
        $templates = VoucherPrintTemplate::orderBy('name')->get();

        return view('voucher-print-templates.index', compact('templates'));
    }

    public function create()
    {
        $template = new VoucherPrintTemplate([
            'paper_size' => '80mm',
            'content' => VoucherPrintTemplate::defaultContent(),
        ]);

        return view('voucher-print-templates.edit', compact('template'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'paper_size' => 'required|in:58mm,80mm,A4',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template = VoucherPrintTemplate::create([
            'name' => $validated['name'],
            'paper_size' => $validated['paper_size'],
            'content' => $validated['content'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        if ($template->is_active) {
            $this->deactivateOthers($template->id);
        }

        ActivityLog::log('Tambah Template Cetak', 'Menambahkan template cetak voucher: '.$template->name);

        return redirect()->route('voucher-print-templates.index')
            ->with('success', 'Template "'.$template->name.'" berhasil ditambahkan.');
    }

    public function edit(VoucherPrintTemplate $template)
    {
        return view('voucher-print-templates.edit', compact('template'));
    }

    public function update(Request $request, VoucherPrintTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'paper_size' => 'required|in:58mm,80mm,A4',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'name' => $validated['name'],
            'paper_size' => $validated['paper_size'],
            'content' => $validated['content'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        if ($template->is_active) {
            $this->deactivateOthers($template->id);
        }

        ActivityLog::log('Ubah Template Cetak', 'Mengubah template cetak voucher: '.$template->name);

        return redirect()->route('voucher-print-templates.index')
            ->with('success', 'Template "'.$template->name.'" berhasil diperbarui.');
    }

    public function destroy(VoucherPrintTemplate $template)
    {
        $name = $template->name;
        $template->delete();

        ActivityLog::log('Hapus Template Cetak', 'Menghapus template cetak voucher: '.$name);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Template "'.$name.'" dihapus.']);
        }

        return back()->with('success', 'Template "'.$name.'" berhasil dihapus.');
    }

    public function activate(VoucherPrintTemplate $template)
    {
        $template->update(['is_active' => true]);
        $this->deactivateOthers($template->id);

        ActivityLog::log('Aktifkan Template Cetak', 'Mengaktifkan template cetak voucher: '.$template->name);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Template "'.$template->name.'" diaktifkan.']);
        }

        return back()->with('success', 'Template "'.$template->name.'" diaktifkan.');
    }

    public function preview(Request $request, ?VoucherPrintTemplate $template = null)
    {
        $template = $template ?? VoucherPrintTemplate::active();

        if (! $template) {
            abort(404, 'Tidak ada template cetak aktif.');
        }

        $sample = $this->sampleData();
        $html = $this->render($template->content, $sample);

        return response($html)->header('Content-Type', 'text/html');
    }

    private function render(string $content, array $data): string
    {
        $map = [
            '{COMPANY}' => $data['company'],
            '{USERNAME}' => $data['username'],
            '{PASSWORD}' => $data['password'],
            '{DURATION}' => $data['duration'],
            '{HOTSPOT_SERVER}' => $data['hotspot_server'],
            '{ADMIN_PHONE}' => $data['admin_phone'],
            '{ADMIN_NAME}' => $data['admin_name'],
        ];

        return str_replace(array_keys($map), array_values($map), $content);
    }

    private function sampleData(): array
    {
        return [
            'company' => 'ALKONEKbill',
            'username' => 'ALK12345',
            'password' => 'pass123',
            'duration' => '7 Hari',
            'hotspot_server' => 'hotspot1',
            'admin_phone' => '62812xxxxxxx',
            'admin_name' => 'Admin',
        ];
    }

    private function deactivateOthers(int $exceptId): void
    {
        VoucherPrintTemplate::where('id', '!=', $exceptId)->update(['is_active' => false]);
    }
}
