<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Builds the LocalBusiness description search engines read, from the identity
 * and contact details the customer filled in.
 *
 * Only fields that are actually filled end up in the output: an empty property
 * is left out rather than published blank.
 */
final class LocalBusiness
{
    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function fromSettings(array $settings, string $siteUrl, ?string $logoUrl = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => self::text($settings, 'nom'),
            'url' => $siteUrl,
        ];

        if ($description = self::text($settings, 'description')) {
            $data['description'] = $description;
        }

        if ($slogan = self::text($settings, 'slogan')) {
            $data['slogan'] = $slogan;
        }

        if ($logoUrl !== null) {
            $data['image'] = $logoUrl;
            $data['logo'] = $logoUrl;
        }

        if ($phone = self::text($settings, 'telephone')) {
            $data['telephone'] = $phone;
        }

        if ($email = self::text($settings, 'email')) {
            $data['email'] = $email;
        }

        if ($address = self::address(self::text($settings, 'adresse'))) {
            $data['address'] = $address;
        }

        if ($siret = self::text($settings, 'siret')) {
            $data['identifier'] = [
                '@type' => 'PropertyValue',
                'propertyID' => 'SIRET',
                'value' => $siret,
            ];
        }

        $hours = $settings['horaires'] ?? null;

        if (is_array($hours) && ($specifications = OpeningHours::toSchema($hours))) {
            $data['openingHoursSpecification'] = $specifications;
        }

        $networks = self::networks($settings['reseaux'] ?? null);

        if ($networks) {
            $data['sameAs'] = $networks;
        }

        return $data;
    }

    /**
     * Splits a postal address typed on several lines.
     *
     * The last line usually carries the postcode and town. When it does not
     * look like one, the whole text stays in the street address rather than
     * being cut at the wrong place.
     *
     * @return array<string, string>|null
     */
    private static function address(string $address): ?array
    {
        if ($address === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $address) ?: [])));

        if ($lines === []) {
            return null;
        }

        $last = end($lines);

        if (count($lines) > 1 && preg_match('/^(\d{5})\s+(.+)$/u', $last, $match) === 1) {
            return [
                '@type' => 'PostalAddress',
                'streetAddress' => implode(', ', array_slice($lines, 0, -1)),
                'postalCode' => $match[1],
                'addressLocality' => $match[2],
                'addressCountry' => 'FR',
            ];
        }

        return [
            '@type' => 'PostalAddress',
            'streetAddress' => implode(', ', $lines),
            'addressCountry' => 'FR',
        ];
    }

    /** @return list<string> */
    private static function networks(mixed $networks): array
    {
        if (!is_array($networks)) {
            return [];
        }

        $urls = [];

        foreach ($networks as $network) {
            $url = is_array($network) ? trim((string) ($network['url'] ?? '')) : '';

            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** @param array<string, mixed> $settings */
    private static function text(array $settings, string $key): string
    {
        $value = $settings[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
