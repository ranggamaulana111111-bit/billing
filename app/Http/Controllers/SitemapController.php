<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('portal.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('vouchers.public.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => route('vouchers.public.check'), 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => route('register'), 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => route('login'), 'priority' => '0.3', 'changefreq' => 'monthly'],
        ];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
