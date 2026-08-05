<?php

declare(strict_types=1);

namespace PasswordPolicy;

/**
 * What counts as an acceptable password for an administration account.
 *
 * Length is what actually resists guessing, so a long passphrase is accepted
 * on its own; shorter passwords must draw on several kinds of characters.
 * The rule is deliberately short: piling up requirements pushes people towards
 * predictable patterns.
 */
final class Policy
{
    public const MINIMUM = 12;

    /** Above this length, a passphrase needs nothing else. */
    public const PASSPHRASE = 16;

    /** Passwords seen far too often to ever be allowed. */
    private const COMMON = [
        'motdepasse', 'password', 'azertyuiop', 'qwertyuiop', 'administrateur',
        'administrator', 'bonjour123', 'password123', 'motdepasse123', '123456789012',
        'azerty123456', 'qwerty123456', 'jetaime123', 'soleil123456',
    ];

    /**
     * Returns the reason a password is refused, or null when it is accepted.
     *
     * @param string $password The password as typed.
     * @param list<string> $personal Values that must not appear in it — user name, e-mail.
     */
    public static function refusal(string $password, array $personal = []): ?string
    {
        $length = mb_strlen($password);

        if ($length < self::MINIMUM) {
            return 'Le mot de passe doit compter au moins '.self::MINIMUM.' caractères.';
        }

        $lowered = mb_strtolower($password);

        foreach (self::COMMON as $common) {
            if (str_contains($lowered, $common)) {
                return 'Ce mot de passe est trop courant. En choisir un autre.';
            }
        }

        foreach ($personal as $value) {

            $value = mb_strtolower(trim($value));

            if (mb_strlen($value) >= 4 && str_contains($lowered, $value)) {
                return 'Le mot de passe ne doit pas contenir le nom du compte ni l’adresse e-mail.';
            }
        }

        if (self::isRepetitive($password)) {
            return 'Le mot de passe ne doit pas être une suite de caractères identiques.';
        }

        // A long passphrase is strong enough by its length alone.
        if ($length >= self::PASSPHRASE) {
            return null;
        }

        if (self::varieties($password) < 3) {
            return 'Entre '.self::MINIMUM.' et '.(self::PASSPHRASE - 1).' caractères, le mot de passe doit mêler '
                .'au moins trois sortes de caractères : minuscules, majuscules, chiffres, signes. '
                .'Au-delà de '.self::PASSPHRASE.' caractères, une phrase suffit.';
        }

        return null;
    }

    /** How many kinds of characters the password draws on. */
    public static function varieties(string $password): int
    {
        $kinds = 0;

        foreach (['/\p{Ll}/u', '/\p{Lu}/u', '/\d/', '/[^\p{L}\d]/u'] as $pattern) {
            if (preg_match($pattern, $password) === 1) {
                $kinds++;
            }
        }

        return $kinds;
    }

    private static function isRepetitive(string $password): bool
    {
        $distinct = count(array_unique(mb_str_split(mb_strtolower($password))));

        return $distinct <= 3;
    }
}
