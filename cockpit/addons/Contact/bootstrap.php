<?php

/**
 * What happens once a contact message arrives.
 *
 * Two things the site itself does not do: warn the customer by e-mail, and
 * offer a way to check that the whole chain works — because an e-mail that
 * never leaves the hosting fails silently, and nobody notices until a
 * customer complains they were never answered.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

declare(strict_types=1);

/** @var Lime\App $this */

$this->on('app.admin.init', function () {
    include __DIR__.'/admin.php';
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer') {
        $assets[] = [
            'src' => 'contact:assets/message-test.js',
            'type' => 'module',
            'position' => 'footer',
        ];
    }
});

$this->on('content.item.save', function (string $modelName, array $item, bool $isUpdate) {

    // Only a message that has just arrived; ticking « lu » must warn nobody.
    if ($modelName !== 'messages' || $isUpdate) {
        return;
    }

    $this->module('contact')->notify($item);
});

$this->module('contact')->extend([

    /**
     * Sends the customer the message that was just received.
     *
     * @param array<string, mixed> $message
     * @return array{envoye: bool, erreur: ?string, destinataire: ?string}
     */
    'notify' => function (array $message): array {

        $settings = $this->app->module('content')->item('settings');
        $to = trim((string) ($settings['email'] ?? ''));

        if ($to === '') {
            return ['envoye' => false, 'erreur' => 'Aucune adresse e-mail dans l’identité du site.', 'destinataire' => null];
        }

        $nom = (string) ($message['nom'] ?? '');
        $from = (string) ($message['email'] ?? '');
        $site = (string) ($settings['nom'] ?? 'le site');

        $body = "Nouveau message reçu depuis {$site}.\n\n"
            ."De : {$nom} <{$from}>\n"
            .'Reçu le : '.($message['envoyeLe'] ?? '')."\n"
            .'Page : '.($message['origine'] ?? '')."\n\n"
            ."Message :\n"
            .($message['message'] ?? '')."\n\n"
            ."— Répondre directement à cet e-mail pour joindre l’expéditeur.\n";

        try {
            $sent = $this->app->mailer->mail($to, "Message reçu depuis {$site}", $body, [
                // Sent from the site's own address so the receiving server
                // accepts it; replying goes to the visitor.
                'from' => $to,
                'from_name' => $site,
                'reply_to' => $from !== '' ? $from : $to,
            ]);

            return ['envoye' => (bool) $sent, 'erreur' => $sent ? null : 'Envoi refusé par le serveur.', 'destinataire' => $to];
        } catch (\Throwable $e) {
            return ['envoye' => false, 'erreur' => $e->getMessage(), 'destinataire' => $to];
        }
    },
]);
