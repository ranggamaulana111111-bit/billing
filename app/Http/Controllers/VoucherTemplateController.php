<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\VoucherTemplate;
use Illuminate\Http\Request;

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
            'content' => 'nullable|string',
            'status_page' => 'nullable|string',
            'redirect_page' => 'nullable|string',
            'error_page' => 'nullable|string',
            'alive_page' => 'nullable|string',
            'logout_page' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $template = VoucherTemplate::create([
            'name' => $validated['name'],
            'content' => $validated['content'] ?? null,
            'status_page' => $validated['status_page'] ?? null,
            'redirect_page' => $validated['redirect_page'] ?? null,
            'error_page' => $validated['error_page'] ?? null,
            'alive_page' => $validated['alive_page'] ?? null,
            'logout_page' => $validated['logout_page'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLog::log('Tambah Template', 'Menambahkan template landing page: '.$template->name);

        return back()->with('success', 'Template "'.$template->name.'" berhasil ditambahkan.');
    }

    public function update(Request $request, VoucherTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status_page' => 'nullable|string',
            'redirect_page' => 'nullable|string',
            'error_page' => 'nullable|string',
            'alive_page' => 'nullable|string',
            'logout_page' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $template->update([
            'name' => $validated['name'],
            'content' => $validated['content'] ?? null,
            'status_page' => $validated['status_page'] ?? null,
            'redirect_page' => $validated['redirect_page'] ?? null,
            'error_page' => $validated['error_page'] ?? null,
            'alive_page' => $validated['alive_page'] ?? null,
            'logout_page' => $validated['logout_page'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLog::log('Ubah Template', 'Mengubah template landing page: '.$template->name);

        return back()->with('success', 'Template "'.$template->name.'" berhasil diperbarui.');
    }

    public function destroy(VoucherTemplate $template)
    {
        $name = $template->name;
        $template->delete();

        ActivityLog::log('Hapus Template', 'Menghapus template landing page: '.$name);

        return back()->with('success', 'Template "'.$name.'" berhasil dihapus.');
    }

    public function preview(VoucherTemplate $template, ?string $page = null)
    {
        $company = Setting::get('company_name', 'ALKONEK');
        $page = $page ?: request('page', 'login');

        $content = $template->getPage($page);

        return view('voucher-templates.preview', compact('template', 'company', 'page', 'content'));
    }
}
