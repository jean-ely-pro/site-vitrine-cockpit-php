<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The addresses the site answers, and what answers them.
 *
 * Written as a table rather than as a chain of conditions: adding an address
 * means adding a line, and reading the table tells the whole story.
 */
final class Route
{
    /** Reserved by the news list and its items; no page may use this slug. */
    public const NEWS = '/actualites';

    /** Where the contact form posts; no page may use this slug either. */
    public const CONTACT = '/contact';

    /**
     * Pages the site writes itself, from the identity and the legal notices.
     *
     * Kept out of the pages collection on purpose: a SIRET or a host that had
     * to be typed a second time is one that ends up wrong in one of the two.
     */
    public const LEGAL = [
        '/mentions-legales' => 'mentions-legales.html.twig',
        '/confidentialite' => 'confidentialite.html.twig',
    ];

    /** Every address a page is forbidden to take. */
    public const RESERVED = [
        self::CONTACT,
        self::NEWS,
        '/mentions-legales',
        '/confidentialite',
        '/sitemap.xml',
        '/robots.txt',
    ];
}
