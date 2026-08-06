<?php

declare(strict_types=1);

namespace App\Contact;

use App\Cockpit\Client;
use App\Cockpit\ContentUnavailable;

/**
 * Receives a contact message and files it.
 *
 * The message is stored through a key allowed to write that one collection
 * and nothing else, so a form left open to everyone can never reach the rest
 * of the site's content.
 */
final class ContactForm
{
    public function __construct(
        private readonly ?Client $writer,
        private readonly SpamGuard $guard,
    ) {
    }

    /** Whether the form can be shown at all. */
    public function isConfigured(): bool
    {
        return $this->writer !== null;
    }

    public function stamp(): string
    {
        return $this->guard->stamp();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function handle(array $input, string $ip): Outcome
    {
        if ($this->writer === null) {
            error_log("[site] formulaire de contact : COCKPIT_WRITE_KEY n'est pas renseignée");

            return Outcome::failure(Submission::fromInput($input), 'indisponible');
        }

        $refusal = $this->guard->refusal($input, $ip);
        $submission = Submission::fromInput($input);

        if ($refusal !== null) {

            // An automated post is told nothing useful and, as far as it can
            // tell, succeeded — there is nothing to learn by trying again.
            if (str_starts_with($refusal, 'spam:')) {
                return Outcome::silentlyDropped($submission);
            }

            return Outcome::failure($submission, $refusal);
        }

        if (!$submission->isValid()) {
            return Outcome::failure($submission, 'invalide');
        }

        try {
            $stored = $this->writer->create('messages', $submission->toItem());
        } catch (ContentUnavailable $e) {
            error_log('[site] message de contact non enregistré : '.$e->getMessage());

            return Outcome::failure($submission, 'indisponible');
        }

        if ($stored === null) {
            return Outcome::failure($submission, 'indisponible');
        }

        $this->guard->record($ip);

        return Outcome::success($submission);
    }
}
