<?php

declare(strict_types=1);

namespace App\View;

/**
 * The customer's colours, turned into a stylesheet the browser can load.
 *
 * Written to a file rather than inlined in the page: the content policy set in
 * public/.htaccess allows styles from the site only, and inlining would mean
 * loosening it for every page.
 *
 * A colour that does not stand out enough against its background is ignored
 * rather than applied. Someone who picks a pale pink for their links should
 * end up with a readable site, not a pretty one nobody can read.
 */
final class Colours
{
    /** Minimum contrast ratio, as required for normal text. */
    public const MINIMUM = 4.5;

    private const DEFAULTS = [
        'lien' => '#8b1f5e',
        'texte' => '#1f2933',
    ];

    public function __construct(private readonly string $file)
    {
    }

    /**
     * Writes the stylesheet, unless it is already there.
     *
     * @param array<string, mixed> $settings
     */
    public function ensure(array $settings): void
    {
        if (is_file($this->file)) {
            return;
        }

        $folder = dirname($this->file);

        if (!is_dir($folder) && !mkdir($folder, 0o755, true) && !is_dir($folder)) {
            error_log("[site] couleurs : {$folder} n'est pas inscriptible");

            return;
        }

        $temporary = $folder.'/.'.basename($this->file).'.'.bin2hex(random_bytes(6));

        if (file_put_contents($temporary, $this->css($settings)) === false) {
            return;
        }

        if (!rename($temporary, $this->file)) {
            @unlink($temporary);
        }
    }

    public function forget(): void
    {
        if (is_file($this->file)) {
            @unlink($this->file);
        }
    }

    /** @param array<string, mixed> $settings */
    public function css(array $settings): string
    {
        $lien = $this->usable($settings['couleurPrincipale'] ?? null, self::DEFAULTS['lien']);
        $texte = $this->usable($settings['couleurTexte'] ?? null, self::DEFAULTS['texte']);

        return ":root{\n"
            ."    --couleur-lien: {$lien};\n"
            ."    --couleur-texte: {$texte};\n"
            ."}\n";
    }

    /**
     * Keeps a colour only if it is a real colour and readable on white.
     */
    private function usable(mixed $colour, string $fallback): string
    {
        if (!is_string($colour) || preg_match('/^#[0-9a-f]{6}$/i', trim($colour)) !== 1) {
            return $fallback;
        }

        $colour = strtolower(trim($colour));

        return self::contrast($colour, '#ffffff') >= self::MINIMUM ? $colour : $fallback;
    }

    /** Contrast ratio between two colours, as WCAG defines it. */
    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        [$high, $low] = $first > $second ? [$first, $second] : [$second, $first];

        return ($high + 0.05) / ($low + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {

            $value = hexdec(substr($hex, $offset, 2)) / 255;

            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
