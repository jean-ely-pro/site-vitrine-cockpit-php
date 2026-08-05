<?php

declare(strict_types=1);

namespace App;

use App\Cockpit\ContentUnavailable;
use App\Content\Blocks;
use App\Content\Repository;
use App\Http\Response;
use App\Media\MediaUrls;
use App\Seo\LocalBusiness;
use App\Seo\Sitemap;
use Twig\Environment;

/**
 * Turns a request path into a fully rendered response.
 *
 * Everything the visitor — or a crawler — needs is in this first response:
 * no content is left for JavaScript to fetch.
 */
final class Application
{
    public function __construct(
        private readonly Repository $content,
        private readonly Environment $twig,
        private readonly MediaUrls $media,
        private readonly Blocks $blocks,
        private readonly string $siteUrl,
        private readonly string $homePageSlug,
    ) {
    }

    public function handle(string $path): Response
    {
        $route = parse_url($path, PHP_URL_PATH) ?: '/';

        try {
            return match ($route) {
                '/sitemap.xml' => $this->sitemap(),
                '/robots.txt' => $this->robots(),
                default => $this->page($route),
            };
        } catch (ContentUnavailable $e) {
            return $this->unavailable($e);
        }
    }

    private function page(string $route): Response
    {
        $slug = $this->slugFromPath($route);

        if ($slug === null) {
            return $this->notFound();
        }

        $page = $this->content->page($slug);

        if ($page === null) {
            return $this->notFound();
        }

        $settings = $this->content->settings();
        $page['blocs'] = $this->blocks->renderable($page['blocs'] ?? null);

        return new Response(
            $this->twig->render('page.html.twig', [
                'site' => $settings,
                'menu' => $this->content->menu(),
                'page' => $page,
                'slug' => $slug,
                'jsonld' => $this->jsonLd($settings),
            ]),
            200,
            self::cacheHeaders('text/html; charset=utf-8'),
        );
    }

    private function sitemap(): Response
    {
        return new Response(
            Sitemap::toXml($this->content->pages(), $this->siteUrl, $this->homePageSlug),
            200,
            self::cacheHeaders('application/xml; charset=utf-8'),
        );
    }

    private function robots(): Response
    {
        return new Response(
            Sitemap::robotsTxt($this->siteUrl),
            200,
            self::cacheHeaders('text/plain; charset=utf-8'),
        );
    }

    /**
     * Headers for a response that will also be stored on disk.
     *
     * Browsers revalidate on every visit, so emptying the cache takes effect
     * at once instead of waiting for a copy held by the visitor to expire.
     * `X-Page-Cache` says who answered: PHP, or the web server from disk.
     *
     * @return array<string, string>
     */
    private static function cacheHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Page-Cache' => 'miss',
        ];
    }

    /**
     * The LocalBusiness description, ready to be dropped into the page.
     *
     * Empty until the site has at least a name: structured data describing a
     * business with no name says nothing and is better left out.
     *
     * @param array<string, mixed> $settings
     */
    private function jsonLd(array $settings): string
    {
        if (trim((string) ($settings['nom'] ?? '')) === '') {
            return '';
        }

        $data = LocalBusiness::fromSettings(
            $settings,
            $this->siteUrl,
            $this->media->absoluteUrl($settings['logo'] ?? null),
        );

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Maps "/" to the home page and rejects anything that is not a slug. */
    private function slugFromPath(string $route): ?string
    {
        $slug = trim($route, '/');

        if ($slug === '') {
            return $this->homePageSlug;
        }

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 ? $slug : null;
    }

    private function notFound(): Response
    {
        $settings = [];
        $menu = [];

        try {
            $settings = $this->content->settings();
            $menu = $this->content->menu();
        } catch (ContentUnavailable) {
            // A missing page must still answer 404, even with no content service.
        }

        return new Response(
            $this->twig->render('404.html.twig', ['site' => $settings, 'menu' => $menu]),
            404,
        );
    }

    private function unavailable(ContentUnavailable $e): Response
    {
        error_log('[site] '.$e->getMessage());

        return new Response(
            $this->twig->render('503.html.twig'),
            503,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Retry-After' => '120',
            ],
        );
    }
}
