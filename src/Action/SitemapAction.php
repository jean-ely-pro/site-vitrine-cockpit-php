<?php

declare(strict_types=1);

namespace App\Action;

use App\Content\Repository;
use App\Http\Response;
use App\Http\Route;
use App\Seo\Sitemap;

/**
 * What search engines are told to look at.
 */
final class SitemapAction
{
    public function __construct(
        private readonly Repository $content,
        private readonly string $siteUrl,
        private readonly string $homePageSlug,
    ) {
    }

    public function xml(): Response
    {
        return new Response(
            Sitemap::toXml($this->entries()),
            200,
            Response::cacheable('application/xml; charset=utf-8'),
        );
    }

    public function robots(): Response
    {
        return new Response(
            Sitemap::robotsTxt($this->siteUrl),
            200,
            Response::cacheable('text/plain; charset=utf-8'),
        );
    }

    /** @return list<array{loc: string, lastmod?: int|null}> */
    private function entries(): array
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
                'lastmod' => $this->modified($page),
            ];
        }

        // Written by the site rather than held in the pages collection, but
        // indexable all the same.
        foreach (array_keys(Route::LEGAL) as $legal) {
            $entries[] = ['loc' => $base.$legal];
        }

        $articles = $this->content->articles();

        if ($articles !== []) {
            $entries[] = ['loc' => $base.Route::NEWS];
        }

        foreach ($articles as $article) {

            $slug = $article['slug'] ?? null;

            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $entries[] = [
                'loc' => $base.Route::NEWS.'/'.$slug,
                'lastmod' => $this->modified($article),
            ];
        }

        return $entries;
    }

    /** @param array<string, mixed> $item */
    private function modified(array $item): ?int
    {
        return isset($item['_modified']) && is_numeric($item['_modified'])
            ? (int) $item['_modified']
            : null;
    }
}
