<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\Repository;
use App\Http\Response;
use App\Http\Route;
use App\View\ViewContext;
use Twig\Environment;

/**
 * The legal pages, written by the site from the identity and the notices.
 */
final class LegalAction
{
    public function __construct(
        private readonly Repository $content,
        private readonly Environment $twig,
        private readonly ViewContext $context,
    ) {
    }

    public function __invoke(string $route): Response
    {
        return new Response(
            $this->twig->render(Route::LEGAL[$route], $this->context->forPage(
                trim($route, '/'),
                ['legal' => $this->content->legal()],
            )),
            200,
            Response::cacheable('text/html; charset=utf-8'),
        );
    }
}
