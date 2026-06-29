<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('login'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => route('register'), 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => route('portal.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
        ];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
