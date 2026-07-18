<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\IsolirTemplate;
use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IsolirController extends Controller
{
    public function index(Customer $customer)
    {
        if ($customer->status !== 'suspended') {
            abort(404);
        }

        $invoice = $customer->invoices()
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        return $this->renderIsolir($customer, $invoice);
    }

    public function byIp(Request $request)
    {
        $clientIp = $request->ip();

        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $routers = collect([null]);
            } else {
                $routers = collect();
            }
        }

        $customer = null;
        foreach ($routers as $router) {
            $mikrotik = $router ? new MikrotikService($router) : new MikrotikService;
            try {
                $active = $mikrotik->getPppActive();
                $session = collect($active)->firstWhere('address', $clientIp);
                if ($session && isset($session['name'])) {
                    $customer = Customer::where('pppoe_username', $session['name'])
                        ->where('status', 'suspended')
                        ->first();
                    if ($customer) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (! $customer) {
            return $this->renderUnknown($clientIp);
        }

        $invoice = $customer->invoices()
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        return $this->renderIsolir($customer, $invoice);
    }

    private function renderIsolir(Customer $customer, $invoice): Response
    {
        $active = IsolirTemplate::active()->first();
        $template = $active ? $active->template_data : null;

        if (! $template || ! isset($template['elements'])) {
            $template = $this->defaultTemplate();
        }

        return $this->renderFromTemplate($template, $customer, $invoice);
    }

    private function renderUnknown(string $clientIp): Response
    {
        $bgStart = Setting::get('isolir_bg_start', '#0f172a');
        $bgEnd = Setting::get('isolir_bg_end', '#1e293b');
        $cardBg = Setting::get('isolir_card_bg', '#ffffff');
        $titleColor = Setting::get('isolir_title_color', '#dc2626');
        $textColor = $this->isLightColor($cardBg) ? '#1e293b' : '#e2e8f0';
        $subColor = $this->isLightColor($cardBg) ? '#64748b' : '#94a3b8';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400">';
        $svg .= '<defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="'.e($bgStart).'"/><stop offset="100%" stop-color="'.e($bgEnd).'"/></linearGradient></defs>';
        $svg .= '<rect width="400" height="400" fill="url(#bg)"/>';
        $svg .= '<rect x="24" y="40" width="352" height="320" rx="20" fill="'.e($cardBg).'"/>';
        $svg .= '<text x="200" y="110" text-anchor="middle" font-size="36" font-family="system-ui,sans-serif">&#x1F6AB;</text>';
        $svg .= '<text x="200" y="150" text-anchor="middle" font-weight="800" font-size="20" fill="'.e($titleColor).'" font-family="system-ui,sans-serif">Akses Dibatasi</text>';
        $svg .= '<text x="200" y="180" text-anchor="middle" font-size="12" fill="'.$subColor.'" font-family="system-ui,sans-serif">Koneksi internet Anda sedang dibatasi.</text>';
        $svg .= '<text x="200" y="200" text-anchor="middle" font-size="12" fill="'.$subColor.'" font-family="system-ui,sans-serif">Silakan hubungi admin.</text>';
        $svg .= '<text x="200" y="240" text-anchor="middle" font-size="10" fill="'.$subColor.'" font-family="system-ui,sans-serif">IP: '.e($clientIp).'</text>';
        $svg .= '</svg>';

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Akses Dibatasi</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:linear-gradient(135deg,'.e($bgStart).','.e($bgEnd).');min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,sans-serif;padding:20px}
svg{max-width:400px;width:100%;height:auto}
</style>
</head>
<body>
'.$svg.'
</body>
</html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderFromTemplate(array $template, Customer $customer, $invoice): Response
    {
        $bg = $template['background'] ?? ['start' => '#0f172a', 'end' => '#1e293b'];
        $card = $template['card'] ?? ['bg' => '#ffffff', 'radius' => 20, 'shadow' => true];
        $elements = $template['elements'] ?? [];
        $adminPhone = Setting::get('admin_phone', '');
        $adminName = Setting::get('admin_name', 'Admin');
        $companyName = Setting::get('company_name', '');

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
                    $lines[] = '<circle cx="'.$cx.'" cy="'.($cy + $sz / 2).'" r="'.($sz / 2 + 8).'" fill="'.e($el['bgColor'] ?? '#dc2626').'" opacity="'.($el['opacity'] ?? 0.12).'"/>';
                    $lines[] = '<text x="'.$cx.'" y="'.($cy + $sz / 2 + 8).'" text-anchor="middle" font-size="'.$sz.'" font-family="system-ui,sans-serif">'.($el['emoji'] ?? '🚫').'</text>';
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
                    $ial = $el['imgAlign'] ?? 'center';
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
                    $lines[] = '<text x="'.($cardPad + $pad).'" y="'.($cy + 38).'" font-size="14" font-weight="600" fill="'.e($el['valueColor'] ?? $defText).'" font-family="system-ui,sans-serif">'.e($customer->name).'</text>';
                    $cy += $bH + 10;
                    break;

                case 'invoice_box':
                    if (! $invoice) {
                        break;
                    }
                    $bH2 = ($el['showAmount'] ?? true) ? 68 : 48;
                    $bR2 = $el['radius'] ?? 10;
                    $pad2 = $el['padding'] ?? 12;
                    $dueDate = Carbon::parse($invoice->due_date ?? $customer->due_date)->format('d M Y');
                    $amount = number_format($invoice->amount, 0, ',', '.');
                    $lines[] = '<rect x="'.$cardPad.'" y="'.$cy.'" width="'.$cw.'" height="'.$bH2.'" rx="'.$bR2.'" fill="'.e($el['bgColor'] ?? $infoBg).'"/>';
                    $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 18).'" font-size="9" fill="'.e($el['labelColor'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($el['label'] ?? 'TAGIHAN').'</text>';
                    $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 36).'" font-size="13" font-weight="600" fill="'.e($el['valueColor'] ?? $defText).'" font-family="system-ui,sans-serif">'.e($invoice->invoice_display).'</text>';
                    if ($el['showAmount'] ?? true) {
                        $lines[] = '<text x="'.($cardPad + $cw - $pad2).'" y="'.($cy + 40).'" text-anchor="end" font-size="16" font-weight="800" fill="'.e($el['amountColor'] ?? '#dc2626').'" font-family="system-ui,sans-serif">Rp '.$amount.'</text>';
                    }
                    if ($el['showDueDate'] ?? true) {
                        $lines[] = '<text x="'.($cardPad + $pad2).'" y="'.($cy + 56).'" font-size="11" fill="'.e($el['labelColor'] ?? $defSub).'" font-family="system-ui,sans-serif">Jatuh Tempo: '.e($dueDate).'</text>';
                    }
                    $cy += $bH2 + 10;
                    break;

                case 'wa_button':
                    if (! $adminPhone) {
                        break;
                    }
                    $waMsg = 'Halo '.$adminName.', saya '.$customer->name.' mau konfirmasi pembayaran';
                    $waUrl = 'https://wa.me/'.$adminPhone.'?text='.rawurlencode($waMsg);
                    $bH3 = 44;
                    $bR3 = $el['radius'] ?? 12;
                    $lines[] = '<a href="'.e($waUrl).'" target="_blank">';
                    $lines[] = '<rect x="'.$cardPad.'" y="'.$cy.'" width="'.$cw.'" height="'.$bH3.'" rx="'.$bR3.'" fill="'.e($el['bgColor'] ?? '#25D366').'"/>';
                    $lines[] = '<text x="'.$cx.'" y="'.($cy + 28).'" text-anchor="middle" font-size="'.($el['size'] ?? 13).'" font-weight="'.($el['weight'] ?? 600).'" fill="'.e($el['textColor'] ?? '#ffffff').'" font-family="system-ui,sans-serif">'.e($el['text'] ?? 'Konfirmasi via WhatsApp').'</text>';
                    $lines[] = '</a>';
                    $cy += $bH3 + 10;
                    break;

                case 'footer':
                    if ($el['showCompany'] ?? true) {
                        if ($companyName) {
                            $lines[] = '<text x="'.$cx.'" y="'.($cy + 12).'" text-anchor="middle" font-size="'.($el['companySize'] ?? 12).'" font-weight="600" fill="'.e($el['companyColor'] ?? $defSub).'" font-family="system-ui,sans-serif">'.e($companyName).'</text>';
                            $cy += ($el['companySize'] ?? 12) + 6;
                        }
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

        $title = 'Halaman Isolir';

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>'.e($title).'</title>
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

    private function defaultTemplate(): array
    {
        return [
            'background' => ['start' => '#0f172a', 'end' => '#1e293b'],
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
