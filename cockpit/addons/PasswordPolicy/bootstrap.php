<?php

/**
 * Password policy for administration accounts.
 *
 * Cockpit ships neither a policy nor a strength meter. This addon adds both:
 * the rule is enforced on the server — the only place that counts — and the
 * meter in the browser only tells the person how they are doing.
 *
 * It is an addon rather than a patch so that updating Cockpit never undoes it.
 */

declare(strict_types=1);

require_once __DIR__.'/Policy.php';

use PasswordPolicy\Policy;

/** @var Lime\App $this */

// The controller hashes the password before it fires any event, so the rule
// has to be applied on the way in, while the password is still readable.
$this->on('app.admin.request', function ($request) {

    if (!str_starts_with($request->route, '/system/users/save')) {
        return;
    }

    // This event fires before the controller checks who is asking. Leave an
    // anonymous request alone so it gets its 401, rather than answering it
    // with a rule it has no business learning.
    if (!$this->helper('auth')->getUser()) {
        return;
    }

    $user = $request->param('user');

    if (!is_array($user)) {
        return;
    }

    $password = $user['password'] ?? null;

    // An empty field on an existing account means « leave it unchanged ».
    if (!is_string($password) || trim($password) === '') {
        return;
    }

    $refusal = Policy::refusal($password, [
        (string) ($user['user'] ?? ''),
        (string) ($user['email'] ?? ''),
    ]);

    if ($refusal !== null) {
        $this->stop(['error' => $refusal], 412);
    }
});

// The meter itself, added to the admin pages.
$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer') {
        $assets[] = [
            'src' => 'passwordpolicy:assets/password-strength.js',
            'type' => 'module',
            'position' => 'footer',
        ];
    }
});
