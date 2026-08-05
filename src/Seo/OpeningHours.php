<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Turns the free-text opening hours the customer types into the structured
 * form search engines expect.
 *
 * The customer writes « 9h – 12h, 14h – 18h30 » or « 09:00-18:00 »; anything
 * that cannot be read with certainty is left out rather than published wrong.
 * Structured data that contradicts the page is worse than no structured data.
 */
final class OpeningHours
{
    private const DAYS = [
        'lundi' => 'Monday',
        'mardi' => 'Tuesday',
        'mercredi' => 'Wednesday',
        'jeudi' => 'Thursday',
        'vendredi' => 'Friday',
        'samedi' => 'Saturday',
        'dimanche' => 'Sunday',
    ];

    /**
     * @param array<string, mixed> $hours Day name => free text.
     * @return list<array{'@type': string, dayOfWeek: string, opens: string, closes: string}>
     */
    public static function toSchema(array $hours): array
    {
        $specifications = [];

        foreach (self::DAYS as $french => $english) {

            $text = $hours[$french] ?? null;

            if (!is_string($text) || trim($text) === '') {
                continue;
            }

            foreach (self::ranges($text) as [$opens, $closes]) {
                $specifications[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => "https://schema.org/{$english}",
                    'opens' => $opens,
                    'closes' => $closes,
                ];
            }
        }

        return $specifications;
    }

    /**
     * Reads every « from – to » range in one day's text.
     *
     * @return list<array{string, string}>
     */
    private static function ranges(string $text): array
    {
        // 9h, 9h30, 09:00, 9.30 — then any dash, then the same again.
        $time = '(\d{1,2})\s*(?:h|:|\.)?\s*(\d{2})?';
        $pattern = "/{$time}\s*(?:-|–|—|à|a)\s*{$time}/iu";

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $ranges = [];

        foreach ($matches as $match) {

            $opens = self::time($match[1], $match[2] ?? '');
            $closes = self::time($match[3], $match[4] ?? '');

            // A range that does not move forward was misread; drop it.
            if ($opens === null || $closes === null || $closes <= $opens) {
                continue;
            }

            $ranges[] = [$opens, $closes];
        }

        return $ranges;
    }

    private static function time(string $hour, string $minute): ?string
    {
        $h = (int) $hour;
        $m = $minute === '' ? 0 : (int) $minute;

        if ($h > 23 || $m > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $m);
    }
}
