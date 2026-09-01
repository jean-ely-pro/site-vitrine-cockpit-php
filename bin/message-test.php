<?php

declare(strict_types=1);

/**
 * Sends a test contact message, from the command line.
 *
 * Same thing as the button in the admin, usable during an installation before
 * anyone has logged in — and it says plainly whether the e-mail left, which is
 * the part that fails quietly on shared hosting.
 *
 * Usage: php bin/message-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

$root = dirname(__DIR__);
$adminDir = "{$root}/public/admin";

if (!is_file("{$adminDir}/bootstrap.php")) {
    fwrite(STDERR, "\n  Cockpit n'est pas installé.\n\n");
    exit(1);
}

require "{$adminDir}/bootstrap.php";

/** @var Lime\App $app */
$app = Cockpit::instance($adminDir);

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n  Échec : {$e->getMessage()}\n\n");
    exit(1);
});

echo "\nMessage de test\n\n";

$content = $app->module('content');

$message = [
    'nom' => 'Message de test',
    'email' => (string) ($content->item('settings')['email'] ?? 'test@localhost'),
    'message' => 'Ceci est un message de test, envoyé en ligne de commande pour vérifier que les '
        .'messages arrivent bien et que la notification par e-mail fonctionne.',
    'consentement' => true,
    'envoyeLe' => date('d/m/Y à H:i'),
    'origine' => 'Ligne de commande — bouton de test',
    'lu' => false,
];

$stored = $content->saveItem('messages', $message);

if (!$stored) {
    fwrite(STDERR, "  Le message n'a pas pu être enregistré.\n\n");
    exit(1);
}

echo "  Enregistré. Il apparaît dans « Messages reçus ».\n";

$mail = $app->module('contact')->notify($message);

if ($mail['envoye']) {
    echo "  E-mail de notification parti vers {$mail['destinataire']}.\n\n";
    exit(0);
}

echo "  E-mail NON parti : {$mail['erreur']}\n";
echo "  Les messages restent consultables dans l'administration, mais personne\n";
echo "  ne sera prévenu.\n";

// Where to look next. Naming the wrong place sends someone digging through the
// hosting's mail for an address that simply has not been filled in.
echo ($mail['cause'] ?? 'envoi') === 'configuration'
    ? "  Rien à chercher du côté de l'hébergement : la cause est dans l'identité\n"
        ."  du site, à corriger dans l'administration.\n\n"
    : "  Vérifier l'envoi de courrier de l'hébergement.\n\n";

// Not a failure of the script: the message is stored. Only the warning failed.
exit(0);
