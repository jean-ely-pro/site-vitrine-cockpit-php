<?php

/**
 * Legal notices.
 *
 * Only what the law requires and the site identity does not already hold: who
 * publishes, who hosts. The rest — name, address, SIRET, e-mail — is read from
 * « Identité du site », so it is written once and cannot fall out of step.
 *
 * Installed to public/admin/storage/content/ by bin/install-cockpit.php.
 */

return [
    'name' => 'legal',
    'label' => 'Mentions légales',
    'info' => 'Les informations obligatoires, reprises sur les pages légales du site.',
    'type' => 'singleton',
    'group' => null,
    'preview' => [],
    'meta' => null,
    '_created' => 1754870400,
    '_modified' => 1754870400,

    'fields' => [

        // ── Éditeur ───────────────────────────────────────────────────────
        [
            'name' => 'directeurPublication',
            'type' => 'text',
            'label' => 'Directeur de la publication',
            'info' => 'La personne responsable du contenu du site. Apparaît dans les mentions légales.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Éditeur',
            'width' => '1-2',
            'opts' => [],
        ],
        [
            'name' => 'formeJuridique',
            'type' => 'text',
            'label' => 'Forme juridique',
            'info' => 'Par exemple : entreprise individuelle, SARL, SAS. Apparaît dans les mentions légales.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Éditeur',
            'width' => '1-2',
            'opts' => ['placeholder' => 'Entreprise individuelle'],
        ],
        [
            'name' => 'tva',
            'type' => 'text',
            'label' => 'Numéro de TVA intracommunautaire',
            'info' => 'Laisser vide si vous n’y êtes pas assujetti.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Éditeur',
            'width' => '1-2',
            'opts' => [],
        ],

        // ── Hébergeur ─────────────────────────────────────────────────────
        [
            'name' => 'hebergeurNom',
            'type' => 'text',
            'label' => 'Nom de l’hébergeur',
            'info' => 'L’entreprise qui héberge le site. Obligatoire dans les mentions légales.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Hébergeur',
            'width' => '1-2',
            'opts' => [],
        ],
        [
            'name' => 'hebergeurAdresse',
            'type' => 'text',
            'label' => 'Adresse de l’hébergeur',
            'info' => 'Adresse postale et téléphone, tels qu’indiqués par l’hébergeur.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Hébergeur',
            'width' => '1-1',
            'opts' => ['multiline' => true],
        ],

        // ── Compléments ───────────────────────────────────────────────────
        [
            'name' => 'complementMentions',
            'type' => 'richtext',
            'label' => 'À ajouter aux mentions légales',
            'info' => 'Facultatif. Ce texte est ajouté à la fin de la page des mentions légales.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Compléments',
            'width' => '1-1',
            'opts' => ['toolbar' => 'format | link | listBullet listOrdered'],
        ],
        [
            'name' => 'complementConfidentialite',
            'type' => 'richtext',
            'label' => 'À ajouter à la politique de confidentialité',
            'info' => 'Facultatif. Ce texte est ajouté à la fin de la page de confidentialité.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Compléments',
            'width' => '1-1',
            'opts' => ['toolbar' => 'format | link | listBullet listOrdered'],
        ],
        [
            'name' => 'dureeConservation',
            'type' => 'text',
            'label' => 'Durée de conservation des messages',
            'info' => 'Indiquée dans la politique de confidentialité. Par exemple : 12 mois.',
            'required' => false,
            'localize' => false,
            'multiple' => false,
            'group' => 'Compléments',
            'width' => '1-2',
            'opts' => ['placeholder' => '12 mois'],
        ],
    ],
];
