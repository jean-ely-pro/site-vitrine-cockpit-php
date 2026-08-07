<?php

declare(strict_types=1);

namespace App\Action;

use App\Content\Repository;
use App\Http\Response;
use App\Http\Route;
use App\Http\ViewContext;
use Twig\Environment;

/**
 * The news list, and one news item.
 */
final class NewsAction
{
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly Repository $content,
        private readonly Environment $twig,
        private readonly ViewContext $context,
    ) {
    }

    /** Most recent first. */
    public function list(): Response
    {
        return new Response(
            $this->twig->render('actualites.html.twig', $this->context->forPage(
                trim(Route::NEWS, '/'),
                ['articles' => $this->content->articles()],
            )),
            200,
            Response::cacheable('text/html; charset=utf-8'),
        );
    }

    /** Null when the slug is unknown or the item is still a draft. */
    public function item(string $slug): ?Response
    {
        if (preg_match(self::SLUG, $slug) !== 1) {
            return null;
        }

        $article = $this->content->article($slug);

        if ($article === null) {
            return null;
        }

        return new Response(
            $this->twig->render('actualite.html.twig', $this->context->forPage(
                trim(Route::NEWS, '/'),
                ['article' => $article],
            )),
            200,
            Response::cacheable('text/html; charset=utf-8'),
        );
    }
}
