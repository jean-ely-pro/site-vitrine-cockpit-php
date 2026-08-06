<?php

declare(strict_types=1);

/**
 * Prepares a fresh Cockpit installation: administrator account, read-only role
 * and API key for the public site, plus demo content.
 *
 * Safe to run twice — anything that already exists is left untouched.
 *
 * Usage: php bin/cockpit-init.php [--password=…]
 */

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute en ligne de commande.\n");
}

$root = dirname(__DIR__);
$adminDir = "{$root}/public/admin";

if (!is_file("{$adminDir}/bootstrap.php")) {
    fwrite(STDERR, "\n  Cockpit n'est pas installé. Lancer d'abord : php bin/install-cockpit.php\n\n");
    exit(1);
}

require "{$adminDir}/bootstrap.php";

/** @var Lime\App $app */
$app = Cockpit::instance($adminDir);

// Cockpit routes uncaught exceptions to a log file. On the command line the
// operator needs to see them, so take the handler back.
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n  Échec : {$e->getMessage()}\n  {$e->getFile()}:{$e->getLine()}\n\n");
    exit(1);
});

function step(string $message): void
{
    echo "  {$message}\n";
}

function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return null;
}

function randomPassword(int $length = 16): string
{
    $alphabet = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $password;
}

/** Writes a key=value pair into the project .env, creating it if needed. */
function writeEnv(string $root, string $key, string $value): void
{
    $file = "{$root}/.env";

    if (!is_file($file)) {
        copy("{$root}/.env.example", $file);
    }

    $content = (string) file_get_contents($file);
    $line = "{$key}={$value}";

    if (preg_match("/^{$key}=.*$/m", $content)) {
        $content = preg_replace("/^{$key}=.*$/m", $line, $content);
    } else {
        $content = rtrim($content, "\n")."\n{$line}\n";
    }

    file_put_contents($file, $content);
}

/**
 * Draws a plain illustration for the demo content.
 *
 * Demo images are generated rather than downloaded: the site must never depend
 * on a third-party host, not even for a placeholder.
 *
 * @param array{int, int, int} $from
 * @param array{int, int, int} $to
 */
function drawDemoImage(string $file, int $width, int $height, array $from, array $to): void
{
    $image = imagecreatetruecolor($width, $height);

    // Vertical gradient.
    for ($y = 0; $y < $height; $y++) {

        $ratio = $y / max(1, $height - 1);
        $colour = imagecolorallocate(
            $image,
            (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
            (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
            (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
        );

        imageline($image, 0, $y, $width, $y, $colour);
    }

    // A few soft discs, for something other than a flat wash.
    imagealphablending($image, true);

    foreach ([[0.24, 0.34, 0.26], [0.68, 0.58, 0.34], [0.86, 0.24, 0.16]] as [$x, $y, $size]) {
        $colour = imagecolorallocatealpha($image, 255, 255, 255, 100);
        imagefilledellipse(
            $image,
            (int) round($width * $x),
            (int) round($height * $y),
            (int) round($width * $size),
            (int) round($width * $size),
            $colour,
        );
    }

    imagewebp($image, $file, 82);
    imagedestroy($image);
}

/**
 * Registers a file as a Cockpit media, through Cockpit's own upload routine.
 *
 * @return array<string, mixed>|null The stored asset.
 */
function addAsset(Lime\App $app, string $file, string $title): ?array
{
    // Cockpit's upload moves the file, so hand it a copy.
    $temporary = $app->path('#tmp:').'/'.basename($file);
    copy($file, $temporary);

    $result = $app->module('assets')->upload([
        'name' => [basename($file)],
        'full_path' => [basename($file)],
        'type' => [mime_content_type($temporary) ?: 'image/webp'],
        'tmp_name' => [$temporary],
        'error' => [0],
        'size' => [filesize($temporary)],
    ], [], false);

    $asset = $result['assets'][0] ?? null;

    if (!is_array($asset)) {
        return null;
    }

    $asset['title'] = $title;
    $app->dataStorage->save('assets', $asset);

    return $asset;
}

echo "\nInitialisation de Cockpit\n\n";

// 1. Administrator account ------------------------------------------------

$password = null;

if (!$app->dataStorage->getCollection('system/users')->count()) {

    $password = option($argv, 'password') ?? randomPassword();
    $now = time();

    // dataStorage->save() takes its payload by reference.
    $user = [
        'active' => true,
        'user' => 'admin',
        'name' => 'Administrateur',
        'email' => 'admin@localhost',
        'password' => $app->hash($password),
        'i18n' => 'fr',
        'role' => 'admin',
        'theme' => 'auto',
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/users', $user);

    $app->trigger('app.system.install');

    step('Compte administrateur « admin » créé.');
} else {
    step('Un compte administrateur existe déjà — inchangé.');
}

// 1b. Customer account ----------------------------------------------------
//
// Created after the role below exists, further down; the account is only
// declared here so the password can be shown once, with the other one.

// 2. Read-only role for the public site -----------------------------------
//
// The site only ever reads. Giving it its own role keeps the write
// permissions out of reach of the key stored on the front end.

$roleId = 'site-public';
$role = $app->dataStorage->findOne('system/roles', ['appid' => $roleId]);

$readPermissions = [
    'content/settings/read' => true,
    'content/pages/read' => true,
    'content/menu/read' => true,
    'content/articles/read' => true,
    'content/legal/read' => true,
];

if (!$role) {

    $now = time();

    $newRole = [
        'appid' => $roleId,
        'name' => 'Site public',
        'info' => 'Lecture seule du contenu affiché par le site. Aucune écriture.',
        'permissions' => $readPermissions,
        'expressions' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/roles', $newRole);

    step('Rôle « Site public » créé (lecture seule).');
} elseif (array_diff_key($readPermissions, $role['permissions'] ?? [])) {

    // A collection added after the role was created must become readable too.
    $role['permissions'] = array_merge($role['permissions'] ?? [], $readPermissions);
    $role['_modified'] = time();
    $app->dataStorage->save('system/roles', $role);

    step('Rôle « Site public » complété pour les nouvelles collections.');
} else {
    step('Le rôle « Site public » existe déjà — inchangé.');
}

// 2b. Editing role for the customer ---------------------------------------
//
// What the customer sees in the admin is exactly what this role allows. It
// carries no permission over the structure of the collections, over user
// accounts or over API keys: the site cannot be broken from here, only filled.

$customerRoleId = 'client';

$customerPermissions = [];

foreach (['settings', 'pages', 'menu', 'articles', 'legal'] as $model) {
    $customerPermissions["content/{$model}/read"] = true;
    $customerPermissions["content/{$model}/update"] = true;
}

// Pages and news items are created and published; identity and menu are
// single items that only ever get updated.
foreach (['pages', 'articles'] as $model) {
    $customerPermissions["content/{$model}/create"] = true;
    $customerPermissions["content/{$model}/publish"] = true;
    $customerPermissions["content/{$model}/delete"] = true;
}

// Received messages: read them, tick them as handled, remove them. Never
// create — only the site does that, through its own key.
$customerPermissions['content/messages/read'] = true;
$customerPermissions['content/messages/update'] = true;
$customerPermissions['content/messages/delete'] = true;

// Images, without which no page can be illustrated.
$customerPermissions['assets/upload'] = true;
$customerPermissions['assets/edit'] = true;

$customerRole = $app->dataStorage->findOne('system/roles', ['appid' => $customerRoleId]);

if (!$customerRole) {

    $now = time();

    $newCustomerRole = [
        'appid' => $customerRoleId,
        'name' => 'Client',
        'info' => 'Rédaction du contenu : identité, pages, menu, actualités et images. '
            .'Ne peut ni modifier la structure du site, ni gérer les comptes ou les clés.',
        'permissions' => $customerPermissions,
        'expressions' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/roles', $newCustomerRole);

    step('Rôle « Client » créé (rédaction seulement).');
} elseif (array_diff_key($customerPermissions, $customerRole['permissions'] ?? [])) {

    $customerRole['permissions'] = array_merge($customerRole['permissions'] ?? [], $customerPermissions);
    $customerRole['_modified'] = time();
    $app->dataStorage->save('system/roles', $customerRole);

    step('Rôle « Client » complété pour les nouvelles collections.');
} else {
    step('Le rôle « Client » existe déjà — inchangé.');
}

// 2c. Customer account ----------------------------------------------------

$customerPassword = null;

if (!$app->dataStorage->findOne('system/users', ['user' => 'client'])) {

    $customerPassword = option($argv, 'mot-de-passe-client') ?? randomPassword(20);
    $now = time();

    $customer = [
        'active' => true,
        'user' => 'client',
        'name' => 'Client',
        'email' => 'client@localhost',
        'password' => $app->hash($customerPassword),
        'i18n' => 'fr',
        'role' => $customerRoleId,
        'theme' => 'auto',
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/users', $customer);

    step('Compte « client » créé.');
} else {
    step('Le compte « client » existe déjà — inchangé.');
}

// 3. Read API key ---------------------------------------------------------

$key = $app->dataStorage->findOne('system/api_keys', ['name' => 'Site public']);

if (!$key) {

    $now = time();
    $token = 'API-'.bin2hex(random_bytes(20));

    $newKey = [
        'key' => $token,
        'name' => 'Site public',
        'role' => $roleId,
        'meta' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/api_keys', $newKey);

    writeEnv($root, 'COCKPIT_API_KEY', $token);

    step('Clé de lecture créée et inscrite dans .env.');
} else {
    step('La clé de lecture existe déjà — inchangée.');
}

// 3b. Write key for the contact form --------------------------------------
//
// The form is open to anyone, so its key may do exactly one thing: file a
// message. It cannot read them back, nor touch any other collection.

$writeKey = $app->dataStorage->findOne('system/api_keys', ['name' => 'Formulaire de contact']);

if (!$writeKey) {

    $now = time();
    $writeRoleId = 'formulaire-contact';

    $writeRole = [
        'appid' => $writeRoleId,
        'name' => 'Formulaire de contact',
        'info' => 'Dépose un message reçu. Ne peut rien lire ni rien modifier d’autre.',
        'permissions' => ['content/messages/create' => true],
        'expressions' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/roles', $writeRole);

    $token = 'API-'.bin2hex(random_bytes(20));

    $newWriteKey = [
        'key' => $token,
        'name' => 'Formulaire de contact',
        'role' => $writeRoleId,
        'meta' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/api_keys', $newWriteKey);

    writeEnv($root, 'COCKPIT_WRITE_KEY', $token);

    step('Clé d’écriture du formulaire créée et inscrite dans .env.');
} else {
    step('La clé d’écriture du formulaire existe déjà — inchangée.');
}

// 4. Demo content ---------------------------------------------------------

$content = $app->module('content');

// An empty singleton still answers with a skeleton of null fields, so test a
// field rather than the item itself.
if (empty($content->item('settings')['nom'])) {

    $content->saveItem('settings', [
        'nom' => 'Atelier Bloom',
        'slogan' => 'Fleuriste artisanal depuis 2015',
        'description' => 'Bouquets et compositions florales réalisés sur commande, à Nantes.',
        'siret' => '000 000 000 00000',
        'couleurPrincipale' => '#8B1F5E',
        'couleurTexte' => '#1F2933',
        'email' => 'contact@example.test',
        'telephone' => '02 40 00 00 00',
        'adresse' => "12 rue des Lilas\n44000 Nantes",
        'horaires' => [
            'lundi' => '',
            'mardi' => '9h – 18h',
            'mercredi' => '9h – 18h',
            'jeudi' => '9h – 18h',
            'vendredi' => '9h – 18h',
            'samedi' => '9h – 12h',
            'dimanche' => '',
        ],
        'reseaux' => [],
    ]);

    step("Identité du site renseignée.");
} else {
    step("L'identité du site est déjà renseignée — inchangée.");
}

// 4b. Demo images ---------------------------------------------------------

$existingAssets = $app->dataStorage->find('assets', ['filter' => ['title' => ['$in' => ['Bandeau', 'Atelier']]]])->toArray();
$assets = array_column($existingAssets, null, 'title');

if (!isset($assets['Bandeau'], $assets['Atelier'])) {

    $tmp = "{$root}/var/tmp/demo";

    if (!is_dir($tmp)) {
        mkdir($tmp, 0o755, true);
    }

    if (!isset($assets['Bandeau'])) {
        drawDemoImage("{$tmp}/bandeau.webp", 1600, 900, [139, 31, 94], [242, 214, 228]);
        $assets['Bandeau'] = addAsset($app, "{$tmp}/bandeau.webp", 'Bandeau');
    }

    if (!isset($assets['Atelier'])) {
        drawDemoImage("{$tmp}/atelier.webp", 1200, 800, [70, 96, 84], [226, 236, 224]);
        $assets['Atelier'] = addAsset($app, "{$tmp}/atelier.webp", 'Atelier');
    }

    step('Images de démonstration créées, en WebP et auto-hébergées.');
} else {
    step('Les images de démonstration existent déjà — inchangées.');
}

// 4c. Demo pages ----------------------------------------------------------

$blocContact = [
    'type' => 'contact',
    'titre' => 'Nous trouver',
    'texte' => '<p>La boutique est ouverte du mardi au samedi. Les commandes peuvent être '
        .'passées par téléphone ou par courriel.</p>',
    'afficherHoraires' => true,
];

$pagesDemo = [
    'accueil' => [
        'titre' => 'Bienvenue à l’Atelier Bloom',
        'slug' => 'accueil',
        'seoTitre' => 'Atelier Bloom, fleuriste artisanal à Nantes',
        'seoDescription' => 'Bouquets de saison composés à la main à Nantes. Compositions pour mariages, '
            .'abonnements pour les entreprises et livraison en centre-ville.',
        '_state' => 1,
        'blocs' => [
            [
                'type' => 'hero',
                'titre' => '',
                'accroche' => 'Des fleurs de saison, achetées chaque matin auprès de producteurs '
                    .'de Loire-Atlantique.',
                'image' => $assets['Bandeau'] ?? null,
                'alt' => 'Composition de fleurs de saison dans les tons roses',
                'boutonTexte' => 'Voir nos services',
                'boutonLien' => '/services',
            ],
            [
                'type' => 'texte-image',
                'titre' => 'Un atelier, pas une chaîne',
                'texte' => '<p>Chaque bouquet est composé à la main, à la commande. Nous travaillons '
                    .'avec six producteurs situés à moins de quarante kilomètres de la boutique.</p>'
                    .'<h3>Ce que cela change</h3>'
                    .'<p>Les fleurs tiennent plus longtemps et les variétés suivent réellement '
                    .'les saisons.</p>',
                'image' => $assets['Atelier'] ?? null,
                'alt' => 'Plan de travail de l’atelier, avec tiges et sécateur',
                'positionImage' => 'droite',
            ],
            $blocContact,
        ],
    ],
    'services' => [
        'titre' => 'Nos services',
        'slug' => 'services',
        'seoTitre' => 'Services : bouquets, mariages et abonnements',
        'seoDescription' => 'Bouquets du jour, compositions pour mariages et abonnements floraux '
            .'pour les entreprises, préparés à Nantes.',
        '_state' => 1,
        'blocs' => [
            [
                'type' => 'texte-image',
                'titre' => 'Bouquets et compositions',
                'texte' => '<p>Bouquets du jour composés selon les arrivages, compositions sur mesure '
                    .'pour les mariages et abonnements hebdomadaires pour les entreprises.</p>'
                    .'<h3>Sur commande</h3>'
                    .'<p>Prévoir deux jours pour les compositions de plus de trente tiges.</p>',
                'image' => $assets['Atelier'] ?? null,
                'alt' => 'Fleurs préparées sur le plan de travail de l’atelier',
                'positionImage' => 'gauche',
            ],
            [
                // Exemple de type de section — voir docs/guide-integration.md
                'type' => 'temoignages',
                'titre' => 'Ce qu’en disent nos clients',
                'introduction' => 'Quelques retours reçus après une commande.',
                'temoignages' => [
                    [
                        'citation' => 'Le bouquet est arrivé le matin même, exactement comme sur la photo.',
                        'auteur' => 'Hélène M.',
                        'fonction' => 'Nantes',
                    ],
                    [
                        'citation' => 'Nous faisons appel à l’atelier chaque semaine pour l’accueil de nos bureaux.',
                        'auteur' => 'Sofiane B.',
                        'fonction' => 'Responsable de site',
                    ],
                ],
            ],
            $blocContact,
        ],
    ],
];

foreach ($pagesDemo as $slug => $data) {

    $existing = $content->item('pages', ['slug' => $slug]);

    if ($existing === null) {
        $content->saveItem('pages', $data);
        step("Page « {$data['titre']} » créée.");
        continue;
    }

    // A page from an earlier version carries no blocks yet: bring it up to
    // date rather than leaving it blank on screen.
    if (empty($existing['blocs'])) {
        $content->saveItem('pages', array_merge($existing, ['blocs' => $data['blocs']]));
        step("Page « {$data['titre']} » complétée avec ses sections.");
        continue;
    }

    step("La page « {$data['titre']} » existe déjà — inchangée.");
}

// 4c ter. Privacy page and contact page -----------------------------------
//
// The form must link to a privacy policy — without it, it is not lawful. A
// short page is created here; generating it from the site identity comes
// later.

// The privacy page is written by the site itself now; an earlier version of
// this script created it as an ordinary page, which would shadow nothing but
// would show up twice in the site map.
if ($content->item('pages', ['slug' => 'confidentialite']) !== null) {
    $content->remove('pages', ['slug' => 'confidentialite']);
    step('Ancienne page « Politique de confidentialité » retirée : le site l’écrit désormais lui-même.');
}

if ($content->item('pages', ['slug' => 'nous-ecrire']) === null) {

    $content->saveItem('pages', [
        'titre' => 'Nous écrire',
        'slug' => 'nous-ecrire',
        'seoDescription' => 'Poser une question ou demander un devis en quelques lignes.',
        '_state' => 1,
        'blocs' => [
            [
                'type' => 'formulaire',
                'titre' => 'Votre message',
                'texte' => '<p>Une question, une commande particulière ? Écrivez-nous, nous '
                    .'répondons sous deux jours ouvrés.</p>',
            ],
            $blocContact,
        ],
    ]);

    step('Page « Nous écrire » créée, avec son formulaire.');
} else {
    step('La page « Nous écrire » existe déjà — inchangée.');
}

// 4c bis. Page templates --------------------------------------------------
//
// Cockpit can duplicate an item, so a template is simply a page left
// unpublished: the customer duplicates it, renames it and publishes it. No
// mechanism to maintain, and the templates evolve like any other page.

$modeles = [
    'modele-services' => [
        'titre' => 'Modèle — Services',
        'sections' => [
            ['titre' => 'Ce que nous proposons', 'texte' => 'Décrire ici la première prestation.'],
            ['titre' => 'Comment cela se passe', 'texte' => 'Décrire ici le déroulement.'],
        ],
    ],
    'modele-a-propos' => [
        'titre' => 'Modèle — À propos',
        'sections' => [
            ['titre' => 'Notre histoire', 'texte' => 'Raconter ici l’origine de l’activité.'],
            ['titre' => 'Notre façon de travailler', 'texte' => 'Décrire ici ce qui vous distingue.'],
        ],
    ],
    'modele-tarifs' => [
        'titre' => 'Modèle — Tarifs',
        'sections' => [
            ['titre' => 'Nos tarifs', 'texte' => 'Présenter ici les prix, par prestation.'],
            ['titre' => 'Ce qui est compris', 'texte' => 'Préciser ici ce qu’inclut chaque tarif.'],
        ],
    ],
];

$modelesCrees = 0;

foreach ($modeles as $slug => $modele) {

    if ($content->item('pages', ['slug' => $slug]) !== null) {
        continue;
    }

    $blocs = [];

    foreach ($modele['sections'] as $index => $section) {
        $blocs[] = [
            'type' => 'texte-image',
            'titre' => $section['titre'],
            'texte' => "<p>{$section['texte']}</p>",
            'positionImage' => $index % 2 === 0 ? 'droite' : 'gauche',
        ];
    }

    $blocs[] = $blocContact;

    $content->saveItem('pages', [
        'titre' => $modele['titre'],
        'slug' => $slug,
        'blocs' => $blocs,
        'seoDescription' => '',
        // Left unpublished: a template is never a page of the site.
        '_state' => 0,
    ]);

    $modelesCrees++;
}

step($modelesCrees > 0
    ? "{$modelesCrees} modèles de page créés, non publiés."
    : 'Les modèles de page existent déjà — inchangés.');

// 4d. Menu ----------------------------------------------------------------

if (empty($content->item('menu')['entrees'])) {

    $entries = [];

    foreach (['accueil' => 'Accueil', 'services' => 'Services', 'nous-ecrire' => 'Nous écrire'] as $slug => $label) {

        $page = $content->item('pages', ['slug' => $slug]);

        if ($page !== null) {
            $entries[] = [
                'libelle' => $label,
                'page' => ['_model' => 'pages', '_id' => $page['_id']],
            ];
        }
    }

    $content->saveItem('menu', ['entrees' => $entries]);

    step('Menu renseigné.');
} else {
    step('Le menu est déjà renseigné — inchangé.');
}

// 4d ter. Legal notices ---------------------------------------------------

if (empty($content->item('legal')['hebergeurNom'])) {

    $content->saveItem('legal', [
        'directeurPublication' => 'Camille Bloom',
        'formeJuridique' => 'Entreprise individuelle',
        'tva' => '',
        'hebergeurNom' => 'À renseigner',
        'hebergeurAdresse' => "Nom, adresse postale et téléphone de l’hébergeur,\n"
            ."tels qu’il les communique.",
        'dureeConservation' => '12 mois',
    ]);

    step('Mentions légales pré-remplies — hébergeur à compléter.');
} else {
    step('Les mentions légales sont déjà renseignées — inchangées.');
}

// 4d bis. Demo news item --------------------------------------------------

if ($content->item('articles', ['slug' => 'portes-ouvertes-de-printemps']) === null) {

    $content->saveItem('articles', [
        'titre' => 'Portes ouvertes de printemps',
        'slug' => 'portes-ouvertes-de-printemps',
        'date' => date('Y-m-d'),
        'categorie' => 'evenement',
        'resume' => 'Deux jours pour découvrir l’atelier, rencontrer nos producteurs et repartir '
            .'avec un bouquet composé sous vos yeux.',
        'image' => $assets['Atelier'] ?? null,
        'alt' => 'Bouquets préparés pour les portes ouvertes',
        'contenu' => '<p>L’atelier ouvre ses portes le premier week-end du mois. '
            .'Deux producteurs de Loire-Atlantique seront présents.</p>'
            .'<h2>Au programme</h2>'
            .'<p>Démonstrations de composition, vente de fleurs coupées et conseils d’entretien.</p>',
        '_state' => 1,
    ]);

    step('Actualité de démonstration publiée.');
} else {
    step('L’actualité de démonstration existe déjà — inchangée.');
}

// 4e. Refresh caches ------------------------------------------------------
//
// Cockpit keeps roles and API keys in its memory store. Writing straight to
// the database leaves that copy stale, and the site would keep being refused.

$app->helper('acl')->cache();
$app->helper('api')->cache();

step('Caches des rôles et des clés rafraîchis.');

// 5. Summary --------------------------------------------------------------

echo "\n";

if ($password !== null || $customerPassword !== null) {

    if ($password !== null) {
        echo "  Administration — identifiant : admin\n";
        echo "                   mot de passe : {$password}\n";
    }

    if ($customerPassword !== null) {
        echo "  Client         — identifiant : client\n";
        echo "                   mot de passe : {$customerPassword}\n";
    }

    echo "\n  À noter maintenant : ils ne sont plus affichés ensuite.\n\n";
}

echo "  Administration : http://localhost:8090/\n";
echo "  Site public    : http://localhost:8080/\n\n";
