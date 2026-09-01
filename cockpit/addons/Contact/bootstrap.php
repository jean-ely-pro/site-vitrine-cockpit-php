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
     * Whether an address belongs to a domain reserved for testing.
     *
     * These are set aside by RFC 2606 and RFC 6761 precisely so that nothing
     * addressed to them ever reaches a real recipient. The demo identity uses
     * one; a site being installed usually still does.
     */
    'isTestAddress' => function (string $address): bool {

        $domain = strtolower(trim(substr(strrchr($address, '@') ?: '', 1)));

        if ($domain === '') {
            return true;
        }

        foreach (['test', 'example', 'invalid', 'localhost', 'local'] as $reserved) {
            if ($domain === $reserved || str_ends_with($domain, ".{$reserved}")) {
                return true;
            }
        }

        return in_array($domain, ['example.com', 'example.net', 'example.org'], true);
    },

    /**
     * Sends the customer the message that was just received.
     *
     * @param array<string, mixed> $message
     * « cause » says where to look when nothing left: « configuration » for
     * something to fill in on this site, « envoi » for the hosting's mail.
     * Sending someone to the wrong one costs an hour on shared hosting.
     *
     * @return array{envoye: bool, erreur: ?string, destinataire: ?string, cause: ?string}
     */
    'notify' => function (array $message): array {

        $settings = $this->app->module('content')->item('settings');
        $to = trim((string) ($settings['email'] ?? ''));

        if ($to === '') {
            return [
                'envoye' => false,
                'erreur' => 'Aucune adresse e-mail dans l’identité du site.',
                'destinataire' => null,
                'cause' => 'configuration',
            ];
        }

        // Nothing leaves towards a domain reserved for documentation and
        // testing. A machine sending mail is often wired to a real account,
        // and a message aimed at a domain that does not exist comes back to
        // whoever sent it — filling a real mailbox with test traffic.
        // Inside a module method, $this is the module — the application is
        // reached through $this->app.
        if ($this->app->module('contact')->isTestAddress($to)) {
            return [
                'envoye' => false,
                'erreur' => "« {$to} » est une adresse de démonstration : aucun e-mail n’a été envoyé. "
                    .'Renseigner une vraie adresse dans « Identité du site » pour recevoir les notifications.',
                'destinataire' => $to,
                'cause' => 'configuration',
            ];
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

            return [
                'envoye' => (bool) $sent,
                'erreur' => $sent ? null : 'Envoi refusé par le serveur.',
                'destinataire' => $to,
                'cause' => $sent ? null : 'envoi',
            ];
        } catch (\Throwable $e) {
            return ['envoye' => false, 'erreur' => $e->getMessage(), 'destinataire' => $to, 'cause' => 'envoi'];
        }
    },
]);
