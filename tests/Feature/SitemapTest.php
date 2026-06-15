<?php

namespace Tests\Feature;

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

    public function test_sitemap_contains_public_urls()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertSee(url('/'), false);
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        $response->assertSee(route('portal.index'), false);
    }
}
