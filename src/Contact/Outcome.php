<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * What became of a submitted form.
 *
 * An automated post counts as accepted: telling it what went wrong would only
 * help it come back better prepared.
 */
final class Outcome
{
    private function __construct(
        public readonly bool $accepted,
        public readonly Submission $submission,
        public readonly ?string $reason,
    ) {
    }

    public static function success(Submission $submission): self
    {
        return new self(true, $submission, null);
    }

    public static function silentlyDropped(Submission $submission): self
    {
        return new self(true, $submission, null);
    }

    public static function failure(Submission $submission, string $reason): self
    {
        return new self(false, $submission, $reason);
    }

    /** The message shown to the visitor when the form was refused. */
    public function message(): string
    {
        return match ($this->reason) {
            'invalide' => 'Le message n’a pas pu être envoyé : vérifier les champs signalés.',
            'debit' => 'Plusieurs messages ont déjà été envoyés depuis cet appareil. Réessayer dans une heure.',
            'expire' => 'Cette page est restée ouverte trop longtemps. La recharger et réessayer.',
            default => 'Le message n’a pas pu être envoyé. Réessayer dans quelques instants, '
                .'ou nous joindre par téléphone.',
        };
    }
}
