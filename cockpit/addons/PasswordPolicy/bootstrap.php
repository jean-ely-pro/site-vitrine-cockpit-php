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

// The controllers hash the password before firing any event, so the rule has
// to be applied on the way in, while the password is still readable.
//
// Two routes set a password: the account form, and the reset link sent by
// e-mail. Covering only the first would leave the rule trivially avoidable.
$this->on('app.admin.request', function ($request) {

    if (str_starts_with($request->route, '/system/users/save')) {

        // This event fires before the controller checks who is asking. Leave
        // an anonymous request alone so it gets its 401, rather than answering
        // it with a rule it has no business learning.
        if (!$this->helper('auth')->getUser()) {
            return;
        }

        $user = $request->param('user');

        if (!is_array($user)) {
            return;
        }

        $this->module('passwordpolicy')->check($user['password'] ?? null, [
            (string) ($user['user'] ?? ''),
            (string) ($user['email'] ?? ''),
        ]);

        return;
    }

    // Reset by e-mail link. Nobody is signed in here — that is the point — so
    // the check runs on the password alone.
    if (str_starts_with($request->route, '/auth/reset')) {
        $this->module('passwordpolicy')->check($request->param('password'));
    }
});

$this->module('passwordpolicy')->extend([

    /**
     * Stops the request when the password does not meet the rule.
     *
     * @param list<string> $personal Values that must not appear in it.
     */
    'check' => function (mixed $password, array $personal = []): void {

        // An empty field on an existing account means « leave it unchanged ».
        if (!is_string($password) || trim($password) === '') {
            return;
        }

        $refusal = Policy::refusal($password, $personal);

        if ($refusal === null) {
            return;
        }

        // This runs before the controller, so the application has no response
        // object yet: build and send one directly. Ending here rather than
        // calling stop() keeps Cockpit's « after » handler from running on a
        // response that was never created.
        $response = new \Lime\Response();
        $response->status = 412;
        $response->mime = 'json';
        $response->body = json_encode(['error' => $refusal], JSON_UNESCAPED_UNICODE);
        $response->flush();

        exit;
    },
]);

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
