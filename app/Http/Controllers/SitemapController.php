<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $base = rtrim((string) config('app.url'), '/');

        $urls = [
            ['loc' => $base.'/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $base.'/portal', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => $base.'/vouchers/public', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => $base.'/vouchers/check', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => $base.'/register', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => $base.'/login', 'priority' => '0.3', 'changefreq' => 'monthly'],
        ];

        $xml = "<?xml"; // dipisah agar tidak di-parse sebagai tag PHP saat compile Blade
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
}
