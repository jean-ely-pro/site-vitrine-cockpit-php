<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contact\ContactForm;
use App\Http\Response;

/**
 * Receives the contact form.
 *
 * On success the visitor is sent back with a plain address, so reloading the
 * page cannot send the message a second time.
 */
final class ContactAction
{
    public function __construct(
        private readonly ContactForm $form,
        private readonly PageAction $page,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @return Response|null Null when there is nothing to receive.
     */
    public function __invoke(array $post, string $ip): ?Response
    {
        if ($post === []) {
            return null;
        }

        $outcome = $this->form->handle($post, $ip);
        $origine = $outcome->submission->origine;

        if ($outcome->accepted) {
            return new Response('', 303, [
                'Location' => $origine.'?message=envoye#formulaire',
                'Cache-Control' => 'no-store',
            ]);
        }

        // The page is shown again, with what was typed and what is missing.
        return ($this->page)($origine, [
            'erreur' => $outcome->message(),
            'champs' => $outcome->submission->errors,
            'valeurs' => $outcome->submission->values(),
        ]);
    }
}
