<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\IsolirTemplate;
use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_short_name' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_holder' => 'nullable|string|max:255',
            'invoice_footer' => 'nullable|string|max:500',
            'mikrotik_host' => 'nullable|string|max:255',
            'mikrotik_port' => 'nullable|integer|min:1|max:65535',
            'mikrotik_user' => 'nullable|string|max:255',
            'mikrotik_password' => 'nullable|string|max:255',
            'mikrotik_hotspot_server' => 'nullable|string|max:255',
            'fonnte_token' => 'nullable|string|max:500',
            'midtrans_server_key' => 'nullable|string|max:500',
            'midtrans_client_key' => 'nullable|string|max:500',
            'midtrans_is_production' => 'nullable|in:0,1',
            'voucher_username_length' => 'nullable|integer|min:4|max:20',
            'voucher_password_length' => 'nullable|integer|min:4|max:20',
            'late_fee_amount' => 'nullable|integer|min:0',
            'late_fee_grace_days' => 'nullable|integer|min:0',
            'default_due_date' => 'nullable|integer|min:1|max:28',
            'admin_phone' => 'nullable|string|max:50',
            'admin_name' => 'nullable|string|max:255',
            'isolir_title' => 'nullable|string|max:255',
            'isolir_subtitle' => 'nullable|string|max:500',
            'isolir_message' => 'nullable|string|max:1000',
            'isolir_bg_start' => 'nullable|string|max:7',
            'isolir_bg_end' => 'nullable|string|max:7',
            'isolir_card_bg' => 'nullable|string|max:7',
            'isolir_title_color' => 'nullable|string|max:7',
            'isolir_accent' => 'nullable|string|max:7',
            'isolir_footer_text' => 'nullable|string|max:255',
            'isolir_show_invoice' => 'nullable|in:0,1',
            'isolir_show_wa' => 'nullable|in:0,1',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::set('company_logo', $path);
        }

        ActivityLog::log('Ubah Pengaturan', 'Pengaturan sistem diperbarui');

        return redirect()->route('settings.index')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function testMikrotik()
    {
        $mikrotik = new MikrotikService;

        if (! $mikrotik->isConfigured()) {
            $router = MikrotikRouter::where('is_active', true)
                ->whereIn('type', ['general'])
                ->first();

            if ($router) {
                $mikrotik = new MikrotikService($router);
            }
        }

        if (! $mikrotik->isConfigured()) {
            ActivityLog::log('Test MikroTik', 'Gagal test koneksi: konfigurasi belum lengkap');

            return back()->with('error', 'Konfigurasi MikroTik belum lengkap (host, user, password).');
        }

        $result = $mikrotik->testConnection();

        if ($result['success']) {
            ActivityLog::log('Test MikroTik', 'Koneksi MikroTik berhasil: '.$result['message']);

            return back()->with('success', $result['message']);
        }

        ActivityLog::log('Test MikroTik', 'Koneksi MikroTik gagal: '.$result['message']);

        return back()->with('error', $result['message']);
    }

    public function isolirEditor()
    {
        $templates = IsolirTemplate::orderByDesc('is_active')->orderBy('name')->get();
        $active = $templates->firstWhere('is_active') ?? $templates->first();

        $templateData = $active ? $active->template_data : $this->defaultIsolirTemplate();

        return view('settings.isolir-editor', [
            'isolirTemplate' => $templateData,
            'templates' => $templates,
            'activeTemplateId' => $active?->id,
        ]);
    }

    public function isolirTemplateList(): JsonResponse
    {
        $templates = IsolirTemplate::orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active', 'created_at']);

        return response()->json(['success' => true, 'templates' => $templates]);
    }

    public function isolirTemplateLoad(Request $request): JsonResponse
    {
        $template = IsolirTemplate::findOrFail($request->id);

        return response()->json(['success' => true, 'template' => $template->template_data]);
    }

    public function isolirTemplateSave(Request $request): JsonResponse
    {
        $template = $request->input('template');
        $name = $request->input('name');

        if (! is_array($template)) {
            return response()->json(['success' => false, 'message' => 'Invalid template data'], 422);
        }

        $id = $request->input('id');
        $isNew = ! $id;

        if ($isNew) {
            $name = $name ?: 'Template '.now()->format('d M Y H:i');
            $tpl = IsolirTemplate::create([
                'name' => $name,
                'template' => json_encode($template),
                'is_active' => true,
            ]);
        } else {
            $tpl = IsolirTemplate::findOrFail($id);
            $tpl->update([
                'name' => $name ?: $tpl->name,
                'template' => json_encode($template),
            ]);
        }

        ActivityLog::log('Simpan Template Isolir', 'Template "'.$tpl->name.'" disimpan');

        return response()->json(['success' => true, 'id' => $tpl->id, 'name' => $tpl->name]);
    }

    public function isolirTemplateActivate(Request $request): JsonResponse
    {
        $tpl = IsolirTemplate::findOrFail($request->id);
        $tpl->activate();

        return response()->json(['success' => true]);
    }

    public function isolirTemplateDelete(Request $request): JsonResponse
    {
        $tpl = IsolirTemplate::findOrFail($request->id);
        $tpl->delete();

        ActivityLog::log('Hapus Template Isolir', 'Template "'.$tpl->name.'" dihapus');

        return response()->json(['success' => true]);
    }

    public function isolirTemplateDuplicate(Request $request): JsonResponse
    {
        $tpl = IsolirTemplate::findOrFail($request->id);
        $clone = IsolirTemplate::create([
            'name' => $tpl->name.' (copy)',
            'template' => $tpl->template,
            'is_active' => false,
        ]);

        return response()->json(['success' => true, 'id' => $clone->id, 'name' => $clone->name]);
    }

    public function isolirImageUpload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $file = $request->file('image');
        $filename = 'isolir_'.time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('isolir', $filename, 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function isolirPreview(): Response
    {
        $active = IsolirTemplate::active()->first();
        $template = $active ? $active->template_data : null;

        if (! $template || ! isset($template['elements'])) {
            $template = $this->defaultIsolirTemplate();
        }

        return $this->renderIsolirFromTemplate($template);
    }

    private function defaultIsolirTemplate(): array
    {
        return [
            'background' => ['start' => '#0f172a', 'end' => '#1e293b', 'opacity' => 1],
            'card' => ['bg' => '#ffffff', 'radius' => 20, 'shadow' => true],
            'elements' => [
                ['type' => 'icon', 'emoji' => '&#x1F6AB;', 'bgColor' => '#dc2626', 'opacity' => 0.12, 'size' => 36],
                ['type' => 'heading', 'text' => 'Internet Terisolir', 'color' => '#dc2626', 'size' => 22, 'weight' => 800, 'align' => 'center'],
                ['type' => 'text', 'text' => "Akun Anda sedang ditangguhkan.\nSilakan hubungi admin untuk pembayaran.", 'color' => '#64748b', 'size' => 13, 'align' => 'center', 'weight' => 400],
                ['type' => 'divider', 'color' => '#e2e8f0', 'width' => 50, 'thickness' => 2, 'style' => 'solid'],
                ['type' => 'spacer', 'height' => 8],
                ['type' => 'customer_name', 'label' => 'NAMA PELANGGAN', 'labelColor' => '#94a3b8', 'valueColor' => '#1e293b', 'bgColor' => '#f1f5f9', 'radius' => 10, 'padding' => 12],
                ['type' => 'invoice_box', 'label' => 'TAGIHAN', 'labelColor' => '#94a3b8', 'valueColor' => '#1e293b', 'amountColor' => '#dc2626', 'bgColor' => '#f1f5f9', 'radius' => 10, 'showAmount' => true, 'showDueDate' => true, 'padding' => 12],
                ['type' => 'wa_button', 'text' => 'Konfirmasi via WhatsApp', 'bgColor' => '#25D366', 'textColor' => '#ffffff', 'size' => 13, 'weight' => 600, 'radius' => 12],
                ['type' => 'footer', 'showCompany' => true, 'companyColor' => '#64748b', 'companySize' => 12, 'text' => 'Internet Cepat & Stabil', 'textColor' => '#94a3b8', 'textSize' => 10],
            ],
        ];
    }

    private function renderIsolirFromTemplate(array $template): Response
    {
        $bg = $template['background'] ?? ['start' => '#0f172a', 'end' => '#1e293b'];
        $card = $template['card'] ?? ['bg' => '#ffffff', 'radius' => 20, 'shadow' => true];
        $elements = $template['elements'] ?? [];

        $W = 400;
        $cardPad = 20;
        $cw = $W - ($cardPad * 2);
        $cx = $W / 2;
        $cy = 30;
        $bgOpacity = $bg['opacity'] ?? 1;
        $light = $this->isLightColor($card['bg'] ?? '#ffffff');
        $defText = $light ? '#1e293b' : '#e2e8f0';
        $defSub = $light ? '#64748b' : '#94a3b8';
        $infoBg = $light ? '#f8fafc' : 'rgba(255,255,255,0.08)';

        $lines = [];
        $lines[] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$W.' {H}" width="'.$W.'">';
        $lines[] = '<defs><linearGradient id="bgr" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="'.e($bg['start'] ?? '#0f172a').'"/><stop offset="100%" stop-color="'.e($bg['end'] ?? '#1e293b').'"/></linearGradient></defs>';
        $lines[] = '<rect width="'.$W.'" height="{H}" fill="url(#bgr)" opacity="'.$bgOpacity.'"/>';

        $flowElements = [];
        $absImages = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'image' && ($el['posMode'] ?? 'flow') === 'absolute') {
                $absImages[] = $el;
            } else {
                $flowElements[] = $el;
            }
        }

        if ($card['shadow'] ?? true) {
            $lines[] = '<rect x="'.$cardPad.'" y="26" width="'.$cw.'" height="12" rx="'.($card['radius'] ?? 20).'" fill="rgba(0,0,0,0.12)"/>';
        }
        $lines[] = '<rect x="'.$cardPad.'" y="20" width="'.$cw.'" height="{CH}" rx="'.($card['radius'] ?? 20).'" fill="'.e($card['bg'] ?? '#ffffff').'"/>';
        $cy = 20 + $cardPad;

        foreach ($flowElements as $el) {
            $type = $el['type'] ?? '';
            $x = $cardPad + 20;
            $w = $cw - 40;

            switch ($type) {
                case 'icon':
                    $sz = $el['size'] ?? 36;
                    $bgc = $el['bgColor'] ?? '#dc2626';
                    $opa = $el['opacity'] ?? 0.12;
                    $lines[] = '<circle cx="'.$cx.'" cy="'.($cy + $sz / 2).'" r="'.($sz / 2 + 8).'" fill="'.e($bgc).'" opacity="'.$opa.'"/>';
                    $emoji = $el['emoji'] ?? '&#x1F6AB;';
                    $lines[] = '<text x="'.$cx.'" y="'.($cy + $sz / 2 + 8).'" text-anchor="middle" font-size="'.$sz.'" font-family="system-ui,sans-serif">'.$emoji.'</text>';
                    $cy += $sz + 32;
                    break;

                case 'heading':
                    $fs = $el['size'] ?? 22;
                    $fw = $el['weight'] ?? 800;
                    $al = $el['align'] ?? 'center';
                    $tx = $al === 'left' ? $x : ($al === 'right' ? ($x + $w) : $cx);
                    $anchor = $al === 'left' ? 'start' : ($al === 'right' ? 'end' : 'middle');
                    $lines[] = '<text x="'.$tx.'" y="'.($cy + $fs).'" text-anchor="'.$anchor.'" font-weight="'.$fw.'" font-size="'.$fs.'" fill="'.e($el['color'] ?? '#dc2626').'" font-family="system-ui,sans-serif">'.e($el['text'] ?? '').'</text>';
                    $cy += $fs + 12;
                    break;

                case 'text':
                    $fs2 = $el['size'] ?? 13;
                    $al2 = $el['align'] ?? 'center';
                    $fw2 = $el['weight'] ?? 400;
                    $tx2 = $al2 === 'left' ? $x : ($al2 === 'right' ? ($x + $w) : $cx);
                    $anch2 = $al2 === 'left' ? 'start' : ($al2 === 'right' ? 'end' : 'middle');
                    foreach (explode("\n", $el['text'] ?? '') as $line) {
                        $lines[] = '<text x="'.$tx2.'" y="'.($cy + $fs2 + 2).'" text-anchor="'.$anch2.'" font-size="'.$fs2.'" font-weight="'.$fw2.'" fill="'.e($el['color'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($line).'</text>';
                        $cy += $fs2 + 4;
                    }
                    $cy += 8;
                    break;

                case 'divider':
                    $dw = ($w * ($el['width'] ?? 50)) / 100;
                    $dx = $cx - $dw / 2;
                    $ds = $el['style'] ?? 'solid';
                    $da = $ds === 'dashed' ? ' stroke-dasharray="8,4"' : ($ds === 'dotted' ? ' stroke-dasharray="2,4"' : '');
                    $lines[] = '<line x1="'.$dx.'" y1="'.($cy + 8).'" x2="'.($dx + $dw).'" y2="'.($cy + 8).'" stroke="'.e($el['color'] ?? '#e2e8f0').'" stroke-width="'.($el['thickness'] ?? 2).'"'.$da.'/>';
                    $cy += 20;
                    break;

                case 'spacer':
                    $cy += $el['height'] ?? 16;
                    break;

                case 'image':
                    if (($el['posMode'] ?? 'flow') !== 'flow') {
                        break;
                    }
                    $iw = min($el['width'] ?? 120, $w);
                    $ial = $el['imgAlign'] ?? ($el['align'] ?? 'center');
                    $ix = $ial === 'left' ? $x : ($ial === 'right' ? ($x + $w - $iw) : $cx - $iw / 2);
                    $ih = $iw * 0.6;
                    if (! empty($el['src'])) {
                        $lines[] = '<image href="'.e($el['src']).'" x="'.$ix.'" y="'.$cy.'" width="'.$iw.'" height="'.$ih.'" rx="'.($el['radius'] ?? 0).'" opacity="'.($el['imgOpacity'] ?? 1).'" preserveAspectRatio="xMidYMid slice"/>';
                    }
                    $cy += $ih + 12;
                    break;

                case 'customer_name':
                    $bH = 56;
                    $bR = $el['radius'] ?? 10;
                    $pad = $el['padding'] ?? 12;
                    $lines[] = '<rect x="'.$cardPad.'" y="'.$cy.'" width="'.$cw.'" height="'.$bH.'" rx="'.$bR.'" fill="'.e($el['bgColor'] ?? $infoBg).'"/>';
                    $lines[] = '<text x="'.($cardPad + $pad).'" y="'.($cy + 18).'" font-size="9" fill="'.e($el['labelColor'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($el['label'] ?? 'NAMA PELANGGAN').'</text>';
                    $lines[] = '<text x="'.($cardPad + $pad).'" y="'.($cy + 38).'" font-size="14" font-weight="600" fill="'.e($el['valueColor'] ?? $defText).'" font-family="system-ui,sans-serif">{CUSTOMER_NAME}</text>';
                    $cy += $bH + 10;
                    break;

                case 'invoice_box':
                    $bH2 = ($el['showAmount'] ?? true) ? 68 : 48;
                    $bR2 = $el['radius'] ?? 10;
                    $pad2 = $el['padding'] ?? 12;
                    $lines[] = '<rect x="'.$cardPad.'" y="'.$cy.'" width="'.$cw.'" height="'.$bH2.'" rx="'.$bR2.'" fill="'.e($el['bgColor'] ?? $infoBg).'"/>';
                    $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 18).'" font-size="9" fill="'.e($el['labelColor'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($el['label'] ?? 'TAGIHAN').'</text>';
                    $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 36).'" font-size="13" font-weight="600" fill="'.e($el['valueColor'] ?? $defText).'" font-family="system-ui,sans-serif">{INVOICE_DISPLAY}</text>';
                    if ($el['showAmount'] ?? true) {
                        $lines[] = '<text x="'.($cardPad + $cw - $pad2).'" y="'.($cy + 40).'" text-anchor="end" font-size="16" font-weight="800" fill="'.e($el['amountColor'] ?? '#dc2626').'" font-family="system-ui,sans-serif">Rp {AMOUNT}</text>';
                    }
                    if ($el['showDueDate'] ?? true) {
                        $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 56).'" font-size="11" fill="'.e($el['labelColor'] ?? $defSub).'" font-family="system-ui,sans-serif">Jatuh Tempo: {DUE_DATE}</text>';
                    }
                    $cy += $bH2 + 10;
                    break;

                case 'wa_button':
                    $bH3 = 44;
                    $bR3 = $el['radius'] ?? 12;
                    $lines[] = '<rect x="'.$cardPad.'" y="'.$cy.'" width="'.$cw.'" height="'.$bH3.'" rx="'.$bR3.'" fill="'.e($el['bgColor'] ?? '#25D366').'"/>';
                    $lines[] = '<text x="'.$cx.'" y="'.($cy + 28).'" text-anchor="middle" font-size="'.($el['size'] ?? 13).'" font-weight="'.($el['weight'] ?? 600).'" fill="'.e($el['textColor'] ?? '#ffffff').'" font-family="system-ui,sans-serif">{WA_LINK}</text>';
                    $cy += $bH3 + 10;
                    break;

                case 'footer':
                    if ($el['showCompany'] ?? true) {
                        $lines[] = '<text x="'.$cx.'" y="'.($cy + 12).'" text-anchor="middle" font-size="'.($el['companySize'] ?? 12).'" font-weight="600" fill="'.e($el['companyColor'] ?? $defSub).'" font-family="system-ui,sans-serif">{COMPANY}</text>';
                        $cy += ($el['companySize'] ?? 12) + 6;
                    }
                    $ft = $el['text'] ?? '';
                    if ($ft) {
                        $lines[] = '<text x="'.$cx.'" y="'.($cy + 10).'" text-anchor="middle" font-size="'.($el['textSize'] ?? 10).'" fill="'.e($el['textColor'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($ft).'</text>';
                        $cy += ($el['textSize'] ?? 10) + 10;
                    }
                    break;
            }
        }

        $cy += 20;

        usort($absImages, function ($a, $b) {
            return ($a['zIndex'] ?? 5) <=> ($b['zIndex'] ?? 5);
        });
        foreach ($absImages as $el) {
            if (empty($el['src'])) {
                continue;
            }
            $iw = min($el['width'] ?? 120, $cw);
            $ix = $cardPad + ($cw * ($el['posX'] ?? 50)) / 100 - $iw / 2;
            $iy = 20 + ($cy * ($el['posY'] ?? 10)) / 100;
            $lines[] = '<image href="'.e($el['src']).'" x="'.round($ix).'" y="'.round($iy).'" width="'.$iw.'" height="'.round($iw * 0.6).'" rx="'.($el['radius'] ?? 0).'" opacity="'.($el['imgOpacity'] ?? 1).'" preserveAspectRatio="xMidYMid slice"/>';
        }

        $svgRaw = implode("\n", $lines);
        $svgRaw = str_replace('{H}', (string) ($cy + 20), $svgRaw);
        $svgRaw = str_replace('{CH}', (string) ($cy - 20), $svgRaw);

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Preview Halaman Isolir</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:linear-gradient(135deg,'.e($bg['start'] ?? '#0f172a').','.e($bg['end'] ?? '#1e293b').');min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,sans-serif;padding:20px}
svg{max-width:400px;width:100%;height:auto}
</style>
</head>
<body>
'.$svgRaw.'
</body>
</html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function isLightColor(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) < 6) {
            return true;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 150;
    }
}
