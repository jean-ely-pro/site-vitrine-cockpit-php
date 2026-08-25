<?php

/**
 * Main navigation.
 *
 * Entries point at existing pages rather than at free-form addresses, so the
 * menu can never link to a page that does not exist. Installed to
 * public/admin/storage/content/ by bin/install-cockpit.php.
 */

return [
    'name' => 'menu',
    'label' => 'Menu',
    'info' => 'Les liens affichés en haut de toutes les pages.',
    'type' => 'singleton',
    'group' => null,
    'preview' => [],
    'meta' => null,
    '_created' => 1754352000,
    '_modified' => 1754352000,

    'fields' => [
        [
            'name' => 'entrees',
            'type' => 'set',
            'label' => 'Entrées du menu',
            'info' => 'Apparaissent en haut de toutes les pages, dans cet ordre.',
            'required' => false,
            'localize' => false,
            'multiple' => true,
            'group' => null,
            'width' => '1-1',
            'opts' => [
                'display' => '${data.libelle || \'Entrée sans libellé\'}',
                'fields' => [
                    [
                        'name' => 'libelle',
                        'type' => 'text',
                        'label' => 'Libellé',
                        'info' => 'Le texte du lien. Vide, le titre de la page est repris.',
                        'width' => '1-2',
                        'opts' => [],
                    ],
                    [
                        'name' => 'page',
                        'type' => 'contentItemLink',
                        'label' => 'Page',
                        'info' => 'La page vers laquelle le lien conduit.',
                        'required' => true,
                        'width' => '1-2',
                        'opts' => ['link' => 'pages', 'display' => '${data.titre || \'Page sans titre\'}'],
                    ],
                ],
            ],
        ],
    ],
];
