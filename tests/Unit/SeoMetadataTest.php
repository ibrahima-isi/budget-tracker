<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeoMetadataTest extends TestCase
{
    public function test_seo_configuration_contains_budget_tracker_identity(): void
    {
        $seo = require __DIR__.'/../../config/seo.php';

        $this->assertSame('BudgetTracker - GUI CONNECT', $seo['title']);
        $this->assertSame('https://budget.gui-connect.com', $seo['url']);
        $this->assertSame('GUI CONNECT', $seo['organization']['name']);
        $this->assertStringContainsString('Budget Tracker', $seo['description']);
    }

    public function test_root_layout_contains_server_rendered_seo_tags(): void
    {
        $layout = file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

        $this->assertStringContainsString('<meta name="description" content="{{ $seoDescription }}">', $layout);
        $this->assertStringContainsString('<link rel="canonical" href="{{ $canonicalUrl }}">', $layout);
        $this->assertStringContainsString('<meta property="og:title" content="{{ $seoTitle }}">', $layout);
        $this->assertStringContainsString('<meta name="twitter:title" content="{{ $seoTitle }}">', $layout);
        $this->assertStringContainsString('application/ld+json', $layout);
        $this->assertStringContainsString('<title inertia>{{ $seoTitle }}</title>', $layout);
    }

    public function test_crawl_files_point_to_public_homepage(): void
    {
        $robots = file_get_contents(__DIR__.'/../../public/robots.txt');
        $sitemap = file_get_contents(__DIR__.'/../../public/sitemap.xml');

        $this->assertStringContainsString('Sitemap: https://budget.gui-connect.com/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('<loc>https://budget.gui-connect.com/</loc>', $sitemap);
    }
}
