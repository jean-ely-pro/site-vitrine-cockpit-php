<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * What someone typed in the contact form, checked.
 *
 * Errors are named per field so the form can be shown again with what was
 * already written: making someone retype everything because of one mistake is
 * how a contact form loses a customer.
 */
final class Submission
{
    private const MAX_NAME = 120;
    private const MAX_MESSAGE = 5000;

    /** @param array<string, string> $errors */
    private function __construct(
        public readonly string $nom,
        public readonly string $email,
        public readonly string $message,
        public readonly bool $consentement,
        public readonly string $origine,
        public readonly array $errors,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $nom = self::text($input['nom'] ?? '');
        $email = self::text($input['email'] ?? '');
        $message = self::text($input['message'] ?? '', self::MAX_MESSAGE);
        $consentement = !empty($input['consentement']);
        $origine = self::text($input['retour'] ?? '/');

        $errors = [];

        if ($nom === '') {
            $errors['nom'] = 'Indiquer un nom.';
        } elseif (mb_strlen($nom) > self::MAX_NAME) {
            $errors['nom'] = 'Ce nom est trop long.';
        }

        if ($email === '') {
            $errors['email'] = 'Indiquer une adresse e-mail.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Cette adresse e-mail ne semble pas valide.';
        }

        if ($message === '') {
            $errors['message'] = 'Écrire un message.';
        } elseif (mb_strlen($message) < 10) {
            $errors['message'] = 'Ce message est trop court pour être compris.';
        }

        if (!$consentement) {
            $errors['consentement'] = 'Cocher la case pour autoriser la réponse.';
        }

        // Only ever a path on this site, never an address elsewhere.
        if (preg_match('#^/[a-z0-9/-]*$#', $origine) !== 1) {
            $origine = '/';
        }

        return new self($nom, $email, $message, $consentement, $origine, $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * The message as it is stored.
     *
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        return [
            'nom' => $this->nom,
            'email' => $this->email,
            'message' => $this->message,
            'consentement' => $this->consentement,
            'envoyeLe' => date('d/m/Y à H:i'),
            'origine' => $this->origine,
            'lu' => false,
        ];
    }

    /** @return array<string, string> */
    public function values(): array
    {
        return [
            'nom' => $this->nom,
            'email' => $this->email,
            'message' => $this->message,
        ];
    }

    private static function text(mixed $value, int $max = 500): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Control characters would only ever come from something automated.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($clean), 0, $max);
    }
}
