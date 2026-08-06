<?php

/**
 * The « send a test message » button, behind the admin.
 *
 * Files a message exactly as the site would, then reports what the e-mail
 * notification did. That is the part that fails silently on shared hosting:
 * the message is safely stored, and nobody is ever told about it.
 */

declare(strict_types=1);

/** @var Lime\App $this */

$this->bind('/contact/message-test', function () {

    if (!$this->helper('auth')->getUser()) {
        return $this->stop(401);
    }

    $content = $this->module('content');

    $message = [
        'nom' => 'Message de test',
        'email' => (string) ($content->item('settings')['email'] ?? 'test@localhost'),
        'message' => 'Ceci est un message de test, envoyé depuis l’administration pour vérifier '
            .'que les messages arrivent bien et que la notification par e-mail fonctionne.',
        'consentement' => true,
        'envoyeLe' => date('d/m/Y à H:i'),
        'origine' => 'Administration — bouton de test',
        'lu' => false,
    ];

    $stored = $content->saveItem('messages', $message);

    if (!$stored) {
        return ['ok' => false, 'texte' => 'Le message de test n’a pas pu être enregistré.'];
    }

    // Saving already fired the notification; asked again here only to report
    // precisely what happened, which the save itself cannot say.
    $mail = $this->module('contact')->notify($message);

    $texte = 'Message de test enregistré. Il apparaît dans « Messages reçus ».';

    $texte .= $mail['envoye']
        ? ' L’e-mail de notification est parti vers '.$mail['destinataire'].'.'
        : ' En revanche, l’e-mail n’est pas parti : '.($mail['erreur'] ?? 'raison inconnue')
            .' Les messages restent consultables ici, mais personne ne sera prévenu.';

    return ['ok' => true, 'envoye' => $mail['envoye'], 'texte' => $texte];
});
