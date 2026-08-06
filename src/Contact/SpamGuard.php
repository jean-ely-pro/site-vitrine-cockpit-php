<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * Keeps automated submissions out, without calling anyone.
 *
 * No third-party service is involved — the site must not hand a visitor's
 * address to a captcha provider just to receive a message. Three plain checks
 * catch the overwhelming majority of automated posts:
 *
 *   1. a field no human ever sees, and therefore never fills;
 *   2. the time taken to fill the form, since a script posts instantly;
 *   3. a limit on how many messages one address may send per hour.
 *
 * None of this bothers a real visitor, and none of it can lock one out for
 * good.
 */
final class SpamGuard
{
    /**
     * Faster than this and it was not typed by a person.
     *
     * This one only bites on a page rendered on the spot. Once a page is
     * served from the cache its stamp is frozen, so the delay measured is the
     * age of the cached copy rather than the time someone spent typing — it
     * will simply always pass. The other two checks do not depend on that.
     */
    private const MIN_SECONDS = 3;

    /**
     * How long a stamp stays good. Generous on purpose: a cached page carries
     * the stamp it was rendered with, and refusing it would turn a working
     * form into a broken one for no gain.
     */
    private const MAX_SECONDS = 7 * 24 * 3600;

    private const MAX_PER_HOUR = 5;

    public function __construct(
        private readonly string $directory,
        private readonly string $secret,
    ) {
    }

    /**
     * A signed timestamp, put in the form when it is displayed.
     *
     * Signed so the delay cannot simply be back-dated by whoever posts.
     */
    public function stamp(?int $now = null): string
    {
        $now ??= time();

        return $now.'.'.hash_hmac('sha256', (string) $now, $this->secret);
    }

    /**
     * Returns why a submission is refused, or null when it may go through.
     *
     * @param array<string, mixed> $input
     */
    public function refusal(array $input, string $ip, ?int $now = null): ?string
    {
        $now ??= time();

        // 1. The field only an automated poster fills.
        if (trim((string) ($input['site_web'] ?? '')) !== '') {
            return 'spam:pot';
        }

        // 2. How long the form was on screen.
        $stamp = (string) ($input['jeton'] ?? '');
        $parts = explode('.', $stamp, 2);

        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return 'spam:jeton';
        }

        [$issued, $signature] = $parts;

        if (!hash_equals(hash_hmac('sha256', $issued, $this->secret), $signature)) {
            return 'spam:jeton';
        }

        $elapsed = $now - (int) $issued;

        // A stamp dated in the future was made up.
        if ($elapsed < 0 || $elapsed < self::MIN_SECONDS) {
            return 'spam:vitesse';
        }

        if ($elapsed > self::MAX_SECONDS) {
            return 'expire';
        }

        // 3. How many were already sent from here.
        if ($this->countRecent($ip, $now) >= self::MAX_PER_HOUR) {
            return 'debit';
        }

        return null;
    }

    /** Records an accepted submission, for the rate limit. */
    public function record(string $ip, ?int $now = null): void
    {
        $now ??= time();
        $file = $this->fileFor($ip);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o755, true) && !is_dir($this->directory)) {
            return;
        }

        $times = $this->readTimes($file, $now);
        $times[] = $now;

        file_put_contents($file, implode("\n", $times), LOCK_EX);
    }

    private function countRecent(string $ip, int $now): int
    {
        return count($this->readTimes($this->fileFor($ip), $now));
    }

    /**
     * Submission times from the last hour; older ones are dropped, which also
     * keeps the files from growing.
     *
     * @return list<int>
     */
    private function readTimes(string $file, int $now): array
    {
        if (!is_file($file)) {
            return [];
        }

        $times = [];

        foreach (explode("\n", (string) file_get_contents($file)) as $line) {

            $time = (int) trim($line);

            if ($time > 0 && $now - $time < 3600) {
                $times[] = $time;
            }
        }

        return $times;
    }

    /**
     * The address is never stored as such: only a fingerprint of it, which
     * is enough to count and tells nothing about who sent what.
     */
    private function fileFor(string $ip): string
    {
        return $this->directory.'/'.hash_hmac('sha256', $ip, $this->secret).'.txt';
    }
}
