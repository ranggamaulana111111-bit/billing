<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use Illuminate\Http\Request;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_returns_valid_xml()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('<loc>', false);
        $response->assertSee('</urlset>', false);
    }

    public function test_billing_sitemap_contains_app_urls()
    {
        $request = Request::create('https://billing.alkonek.online/sitemap.xml', 'GET');
        $controller = new SitemapController;
        $response = $controller->index($request);

        $this->assertStringContainsString(route('portal.index'), $response->getContent());
        $this->assertStringContainsString(route('register'), $response->getContent());
        $this->assertStringContainsString(route('login'), $response->getContent());
    }

    public function test_landing_sitemap_only_contains_welcome()
    {
        $request = Request::create('https://alkonek.online/sitemap.xml', 'GET');
        $controller = new SitemapController;
        $response = $controller->index($request);

        $this->assertStringContainsString('https://alkonek.online/', $response->getContent());
        $this->assertStringNotContainsString('https://alkonek.online/portal', $response->getContent());
        $this->assertStringNotContainsString('https://alkonek.online/login', $response->getContent());
    }
}
