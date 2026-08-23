<?php

declare(strict_types=1);

namespace App;

use App\Controller\ContactAction;
use App\Controller\LegalAction;
use App\Controller\NewsAction;
use App\Controller\PageAction;
use App\Controller\SitemapAction;
use App\Cockpit\ContentUnavailable;
use App\Http\Response;
use App\Http\Route;
use App\View\ViewContext;
use Twig\Environment;

/**
 * Turns a request into a response: reads the address, hands it to whoever
 * answers it, and falls back when nobody does.
 *
 * Everything the visitor — or a crawler — needs is in this first response:
 * no content is left for JavaScript to fetch.
 */
final class Application
{
    public function __construct(
        private readonly PageAction $page,
        private readonly NewsAction $news,
        private readonly LegalAction $legal,
        private readonly ContactAction $contact,
        private readonly SitemapAction $sitemap,
        private readonly Environment $twig,
        private readonly ViewContext $context,
    ) {
    }

    /**
     * @param array<string, mixed> $post Contents of a form submission, if any.
     */
    public function handle(string $path, array $post = [], string $ip = ''): Response
    {
        $route = parse_url($path, PHP_URL_PATH) ?: '/';
        parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $query);

        // Only set when there is something to say, so an ordinary page keeps
        // its usual cache headers.
        $notice = ($query['message'] ?? '') === 'envoye' ? ['envoye' => true] : [];

        try {
            $response = $this->dispatch($route, $post, $ip, $notice);
        } catch (ContentUnavailable $e) {
            return $this->unavailable($e);
        }

        return $response ?? $this->notFound();
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $notice
     */
    private function dispatch(string $route, array $post, string $ip, array $notice): ?Response
    {
        return match (true) {
            $route === Route::CONTACT => ($this->contact)($post, $ip),
            isset(Route::LEGAL[$route]) => ($this->legal)($route),
            $route === '/sitemap.xml' => $this->sitemap->xml(),
            $route === '/robots.txt' => $this->sitemap->robots(),
            $route === Route::NEWS, $route === Route::NEWS.'/' => $this->news->list(),
            str_starts_with($route, Route::NEWS.'/') => $this->news->item(substr($route, strlen(Route::NEWS) + 1)),
            default => ($this->page)($route, $notice),
        };
    }

    private function notFound(): Response
    {
        $variables = ['site' => [], 'menu' => []];

        try {
            // No structured data on an error page: describing the business on
            // a page that does not exist tells a search engine nothing useful,
            // and the page is marked as not to be indexed anyway.
            $variables = $this->context->forPage('', ['jsonld' => '']);
        } catch (ContentUnavailable) {
            // A missing page must still answer 404, even with no content service.
        }

        return new Response($this->twig->render('404.html.twig', $variables), 404);
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
