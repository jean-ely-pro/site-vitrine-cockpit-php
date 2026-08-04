<?php

declare(strict_types=1);

/**
 * Prepares a fresh Cockpit installation: administrator account, read-only role
 * and API key for the public site, plus demo content.
 *
 * Safe to run twice — anything that already exists is left untouched.
 *
 * Usage: php bin/cockpit-init.php [--password=…] [--demo]
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

// 2. Read-only role for the public site -----------------------------------
//
// The site only ever reads. Giving it its own role keeps the write
// permissions out of reach of the key stored on the front end.

$roleId = 'site-public';
$role = $app->dataStorage->findOne('system/roles', ['appid' => $roleId]);

if (!$role) {

    $now = time();

    $newRole = [
        'appid' => $roleId,
        'name' => 'Site public',
        'info' => 'Lecture seule du contenu affiché par le site. Aucune écriture.',
        'permissions' => [
            'content/settings/read' => true,
            'content/pages/read' => true,
        ],
        'expressions' => [],
        '_created' => $now,
        '_modified' => $now,
    ];

    $app->dataStorage->save('system/roles', $newRole);

    step('Rôle « Site public » créé (lecture seule).');
} else {
    step('Le rôle « Site public » existe déjà — inchangé.');
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

    step('Identité du site renseignée.');
} else {
    step("L'identité du site est déjà renseignée — inchangée.");
}

if (!$content->item('pages', ['slug' => 'accueil'])) {

    $content->saveItem('pages', [
        'titre' => 'Bienvenue à l’Atelier Bloom',
        'slug' => 'accueil',
        'contenu' => '<p>Nous composons des bouquets à la main, avec des fleurs de saison '
            .'achetées chaque matin auprès de producteurs de Loire-Atlantique.</p>'
            .'<h2>Nos compositions</h2>'
            .'<p>Bouquets du jour, compositions pour mariages et abonnements pour les entreprises.</p>',
        'seoTitre' => 'Atelier Bloom, fleuriste artisanal à Nantes',
        'seoDescription' => 'Bouquets de saison composés à la main à Nantes. Compositions pour mariages, '
            .'abonnements pour les entreprises et livraison en centre-ville.',
        '_state' => 1,
    ]);

    step('Page d’accueil de démonstration créée.');
} else {
    step('La page d’accueil existe déjà — inchangée.');
}

// 5. Summary --------------------------------------------------------------

echo "\n";

if ($password !== null) {
    echo "  Identifiant : admin\n";
    echo "  Mot de passe : {$password}\n";
    echo "  À noter maintenant : il n'est plus affiché ensuite.\n\n";
}

echo "  Administration : http://localhost:8090/\n";
echo "  Site public    : http://localhost:8080/\n\n";
