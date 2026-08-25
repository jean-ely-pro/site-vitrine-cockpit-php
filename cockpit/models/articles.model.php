<?php

/**
 * News items.
 *
 * Fixed structure: the customer fills it in, never edits it. Installed to
 * public/admin/storage/content/ by bin/install-cockpit.php.
 *
 * Publication is not a field: Cockpit tracks it natively on `_state`
 * (1 published, 0 unpublished, -1 archived) with its own button in the admin.
 * The public site only ever serves items with `_state` = 1.
 */

// Headings only, plus links and lists: the page title is the only level-one
// heading, and the toolbar must not offer anything that would break that.
$toolbar = 'format | link | listBullet listOrdered';

return [
    'name' => 'articles',
    'label' => 'Actualités',
    'info' => 'Les actualités publiées sur le site.',
    'type' => 'collection',
    'group' => null,
    'preview' => [],
    'meta' => [
        'unique' => ['slug'],
    ],
    '_created' => 1754524800,
    '_modified' => 1754524800,

    'fields' => [
        [
            'name' => 'titre',
            'type' => 'text',
            'label' => 'Titre',
            'info' => 'Apparaît en tête de l’actualité et dans la liste des actualités.',
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => [],
        ],
        [
            'name' => 'slug',
            'type' => 'text',
            'label' => 'Adresse de l’actualité',
            'info' => 'Termine l’adresse : « portes-ouvertes » donne /actualites/portes-ouvertes. '
                .'Lettres minuscules et tirets.',
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-2',
            'opts' => ['placeholder' => 'portes-ouvertes'],
        ],
        [
            'name' => 'date',
            'type' => 'date',
            'label' => 'Date',
            'info' => 'Affichée sous le titre et sert à classer la liste, la plus récente en premier.',
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-2',
            'opts' => [],
        ],
        [
            'name' => 'categorie',
            'type' => 'select',
            'label' => 'Catégorie',
            'info' => 'Affichée à côté de la date et permet de filtrer la liste.',
            'required' => true,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-2',
            'opts' => [
                'options' => [
                    ['value' => 'actualite', 'label' => 'Actualité'],
                    ['value' => 'evenement', 'label' => 'Événement'],
                    ['value' => 'conseil', 'label' => 'Conseil'],
                ],
            ],
        ],
        [
            'name' => 'resume',
            'type' => 'text',
            'label' => 'Résumé',
            'info' => 'Apparaît dans la liste des actualités et dans les résultats de recherche. '
                .'Environ 155 caractères.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => ['multiline' => true, 'maxlength' => 160, 'showCount' => true],
        ],
        [
            'name' => 'image',
            'type' => 'asset',
            'label' => 'Image',
            'info' => 'Apparaît en tête de l’actualité et dans la liste. Format paysage conseillé.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-2',
            'opts' => ['filter' => ['type' => 'image']],
        ],
        [
            'name' => 'alt',
            'type' => 'text',
            'label' => 'Description de l’image',
            'info' => 'Lue à voix haute par les lecteurs d’écran, et affichée si l’image ne charge pas. '
                .'Décrire ce que l’on voit.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-2',
            'opts' => ['maxlength' => 150],
        ],
        [
            'name' => 'contenu',
            'type' => 'wysiwyg',
            'label' => 'Texte',
            'info' => 'Le corps de l’actualité.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Contenu',
            'width' => '1-1',
            'opts' => ['toolbar' => $toolbar],
        ],
    ],
];
