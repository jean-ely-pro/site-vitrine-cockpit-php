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
    /** Reserved by the news list and its items; no page may use this slug. */
    public const NEWS = '/actualites';

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

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
            return match (true) {
                $route === '/sitemap.xml' => $this->sitemap(),
                $route === '/robots.txt' => $this->robots(),
                $route === self::NEWS || $route === self::NEWS.'/' => $this->newsList(),
                str_starts_with($route, self::NEWS.'/') => $this->newsItem(substr($route, strlen(self::NEWS) + 1)),
                default => $this->page($route),
            };
        } catch (ContentUnavailable $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * The news list, most recent first.
     */
    private function newsList(): Response
    {
        return new Response(
            $this->twig->render(
                'actualites.html.twig',
                $this->shared(trim(self::NEWS, '/')) + ['articles' => $this->content->articles()],
            ),
            200,
            self::cacheHeaders('text/html; charset=utf-8'),
        );
    }

    private function newsItem(string $slug): Response
    {
        if (preg_match(self::SLUG, $slug) !== 1) {
            return $this->notFound();
        }

        $article = $this->content->article($slug);

        if ($article === null) {
            return $this->notFound();
        }

        return new Response(
            $this->twig->render(
                'actualite.html.twig',
                $this->shared(trim(self::NEWS, '/')) + ['article' => $article],
            ),
            200,
            self::cacheHeaders('text/html; charset=utf-8'),
        );
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

        $page['blocs'] = $this->blocks->renderable($page['blocs'] ?? null);

        return new Response(
            $this->twig->render('page.html.twig', $this->shared($slug) + ['page' => $page]),
            200,
            self::cacheHeaders('text/html; charset=utf-8'),
        );
    }

    /**
     * What every page needs: identity, menu, structured data.
     *
     * @return array<string, mixed>
     */
    private function shared(string $slug): array
    {
        $settings = $this->content->settings();

        return [
            'site' => $settings,
            'menu' => $this->content->menu(),
            'afficherActualites' => $this->content->hasArticles(),
            'slug' => $slug,
            'jsonld' => $this->jsonLd($settings),
        ];
    }

    private function sitemap(): Response
    {
        $base = rtrim($this->siteUrl, '/');
        $entries = [];

        foreach ($this->content->pages() as $page) {

            $slug = $page['slug'] ?? null;

            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $entries[] = [
                // The home page is served at the root, not under its slug.
                'loc' => $slug === $this->homePageSlug ? "{$base}/" : "{$base}/{$slug}",
                'lastmod' => isset($page['_modified']) && is_numeric($page['_modified'])
                    ? (int) $page['_modified']
                    : null,
            ];
        }

        $articles = $this->content->articles();

        if ($articles !== []) {
            $entries[] = ['loc' => $base.self::NEWS];
        }

        foreach ($articles as $article) {

            $slug = $article['slug'] ?? null;

            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $entries[] = [
                'loc' => $base.self::NEWS.'/'.$slug,
                'lastmod' => isset($article['_modified']) && is_numeric($article['_modified'])
                    ? (int) $article['_modified']
                    : null,
            ];
        }

        return new Response(
            Sitemap::toXml($entries),
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

        return preg_match(self::SLUG, $slug) === 1 ? $slug : null;
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
