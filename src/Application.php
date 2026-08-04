<?php

declare(strict_types=1);

namespace App;

use App\Cockpit\ContentUnavailable;
use App\Content\Repository;
use App\Http\Response;
use Twig\Environment;

/**
 * Turns a request path into a fully rendered HTML page.
 *
 * Everything the visitor — or a crawler — needs is in this first response:
 * no content is left for JavaScript to fetch.
 */
final class Application
{
    public function __construct(
        private readonly Repository $content,
        private readonly Environment $twig,
        private readonly string $homePageSlug,
    ) {
    }

    public function handle(string $path): Response
    {
        $slug = $this->slugFromPath($path);

        if ($slug === null) {
            return $this->notFound();
        }

        try {
            $page = $this->content->page($slug);

            if ($page === null) {
                return $this->notFound();
            }

            return new Response($this->twig->render('page.html.twig', [
                'site' => $this->content->settings(),
                'page' => $page,
            ]));
        } catch (ContentUnavailable $e) {
            return $this->unavailable($e);
        }
    }

    /** Maps "/" to the home page and rejects anything that is not a slug. */
    private function slugFromPath(string $path): ?string
    {
        $slug = trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        if ($slug === '') {
            return $this->homePageSlug;
        }

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 ? $slug : null;
    }

    private function notFound(): Response
    {
        $settings = [];

        try {
            $settings = $this->content->settings();
        } catch (ContentUnavailable) {
            // A missing page must still answer 404, even with no content service.
        }

        return new Response(
            $this->twig->render('404.html.twig', ['site' => $settings]),
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
