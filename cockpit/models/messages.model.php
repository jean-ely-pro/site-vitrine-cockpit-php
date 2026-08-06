<?php

/**
 * Messages sent through the contact form.
 *
 * Written by the site, read by the customer. Nothing here is ever published:
 * the public site has no permission on this collection at all.
 *
 * Installed to public/admin/storage/content/ by bin/install-cockpit.php.
 */

return [
    'name' => 'messages',
    'label' => 'Messages reçus',
    'info' => 'Les messages envoyés depuis le formulaire de contact du site.',
    'type' => 'collection',
    'group' => null,
    'preview' => [],
    'meta' => null,
    '_created' => 1754784000,
    '_modified' => 1754784000,

    'fields' => [
        [
            'name' => 'lu',
            'type' => 'boolean',
            'label' => 'Lu',
            'info' => 'À cocher une fois le message traité.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-1',
            'opts' => ['default' => false],
        ],
        [
            'name' => 'nom',
            'type' => 'text',
            'label' => 'Nom',
            'info' => 'Saisi par la personne qui écrit.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-2',
            'opts' => ['readonly' => true],
        ],
        [
            'name' => 'email',
            'type' => 'text',
            'label' => 'Adresse e-mail',
            'info' => 'L’adresse à laquelle répondre.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-2',
            'opts' => ['readonly' => true],
        ],
        [
            'name' => 'message',
            'type' => 'text',
            'label' => 'Message',
            'info' => 'Le texte envoyé.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-1',
            'opts' => ['multiline' => true, 'readonly' => true],
        ],
        [
            'name' => 'consentement',
            'type' => 'boolean',
            'label' => 'Consentement donné',
            'info' => 'Case cochée par la personne au moment de l’envoi. Conservée comme preuve.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-2',
            'opts' => ['readonly' => true],
        ],
        [
            'name' => 'envoyeLe',
            'type' => 'text',
            'label' => 'Envoyé le',
            'info' => 'Date et heure de réception.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-2',
            'opts' => ['readonly' => true],
        ],
        [
            'name' => 'origine',
            'type' => 'text',
            'label' => 'Page d’origine',
            'info' => 'La page depuis laquelle le message a été envoyé.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => null,
            'width' => '1-1',
            'opts' => ['readonly' => true],
        ],
    ],
];
