<?php

declare(strict_types=1);

namespace App\Twig;

use App\Media\Picture;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The few helpers templates need that are not presentation: images and
 * clickable contact details.
 *
 * `image()` is the only way a template reaches a media, so no page can end up
 * serving a full-size original or an image without its dimensions.
 */
final class SiteExtension extends AbstractExtension
{
    public function __construct(private readonly Picture $picture)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image', $this->picture->from(...)),
            new TwigFunction('tel_href', $this->telHref(...)),
        ];
    }

    /**
     * Turns a phone number as typed into a dialable address.
     *
     * A French number written « 02 40 00 00 00 » becomes +33240000000, which
     * works from abroad too. Anything unexpected is only stripped of its
     * spaces rather than reshaped into something wrong.
     */
    public function telHref(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if (preg_match('/^0\d{9}$/', $digits) === 1) {
            return '+33'.substr($digits, 1);
        }

        return $digits;
    }
}
