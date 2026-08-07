<?php

declare(strict_types=1);

namespace App\Action;

use App\Content\Blocks;
use App\Content\Repository;
use App\Http\Response;
use App\Http\ViewContext;
use Twig\Environment;

/**
 * A page of the site, made of the sections the customer arranged.
 */
final class PageAction
{
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly Repository $content,
        private readonly Environment $twig,
        private readonly ViewContext $context,
        private readonly Blocks $blocks,
        private readonly string $homePageSlug,
    ) {
    }

    /**
     * @param array<string, mixed> $formulaire What to show back on the form.
     */
    public function __invoke(string $route, array $formulaire = []): ?Response
    {
        $slug = $this->slugFromPath($route);

        if ($slug === null) {
            return null;
        }

        $page = $this->content->page($slug);

        if ($page === null) {
            return null;
        }

        $page['blocs'] = $this->blocks->renderable($page['blocs'] ?? null);

        return new Response(
            $this->twig->render('page.html.twig', $this->context->forPage($slug, [
                'page' => $page,
                'formulaire' => $formulaire,
            ])),
            200,
            // A page showing the result of a submission is personal to that
            // visitor and must never be stored for the next one.
            $formulaire === []
                ? Response::cacheable('text/html; charset=utf-8')
                : ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
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
}
