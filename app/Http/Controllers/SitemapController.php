<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SitemapController extends Controller
{
    public function index(Request $request)
    {
        $base = $this->requestBase($request);
        $urls = $this->urlsForHost($request->getHost(), $base);

        $xml = '<?xml'; // dipisah agar tidak di-parse sebagai tag PHP saat compile Blade
        $xml .= ' version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '    <url>'."\n";
            $xml .= '        <loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8').'</loc>'."\n";
            $xml .= '        <priority>'.$url['priority'].'</priority>'."\n";
            $xml .= '        <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            $xml .= '    </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Base URL sitemap sesuai host request.
     *  - Landing (alkonek.online / www) -> https://alkonek.online
     *  - App (billing.alkonek.online)  -> config('app.url')
     */
    private function requestBase(Request $request): string
    {
        $host = strtolower((string) $request->getHost());

        if (! str_contains($host, 'billing.')) {
            return 'https://alkonek.online';
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Daftar URL per host. Landing hanya berisi halaman welcome ("/").
     * App berisi halaman-halaman utama aplikasi (tanpa "/" yg canonical di landing).
     */
    private function urlsForHost(string $host, string $base): array
    {
        if (! str_contains(strtolower($host), 'billing.')) {
            return [
                ['loc' => $base.'/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ];
        }

        return [
            ['loc' => $base.'/portal', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => $base.'/vouchers/public', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => $base.'/vouchers/check', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => $base.'/register', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => $base.'/login', 'priority' => '0.3', 'changefreq' => 'monthly'],
        ];
    }
}
