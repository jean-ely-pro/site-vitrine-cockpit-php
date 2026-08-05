<?php

declare(strict_types=1);

/**
 * Creates an API key limited to named collections.
 *
 * Each key carries a role, and the role is what the key may do. There is no
 * general-purpose key: the public site reads and cannot write, and anything
 * that needs to write gets its own key, restricted to what it writes.
 *
 * Usage:
 *   php bin/cockpit-cle.php --nom="Formulaire de contact" --ecriture=messages
 *   php bin/cockpit-cle.php --nom="Export" --lecture=pages,articles
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

function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return null;
}

/** @return list<string> */
function models(?string $list): array
{
    if ($list === null || trim($list) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $list))));
}

$name = option($argv, 'nom');
$read = models(option($argv, 'lecture'));
$write = models(option($argv, 'ecriture'));

if ($name === null || trim($name) === '') {
    fwrite(STDERR, "\n  Usage : php bin/cockpit-cle.php --nom=\"…\" [--lecture=a,b] [--ecriture=c]\n\n");
    exit(1);
}

if ($read === [] && $write === []) {
    fwrite(STDERR, "\n  Préciser au moins --lecture ou --ecriture : une clé sans droit ne sert à rien.\n\n");
    exit(1);
}

// Writing implies reading back what was written.
$permissions = [];

foreach ($read as $model) {
    $permissions["content/{$model}/read"] = true;
}

foreach ($write as $model) {
    $permissions["content/{$model}/read"] = true;
    $permissions["content/{$model}/create"] = true;
    $permissions["content/{$model}/update"] = true;
}

$content = $app->module('content');
$unknown = array_values(array_filter([...$read, ...$write], static fn (string $m): bool => !$content->model($m)));

if ($unknown) {
    fwrite(STDERR, "\n  Collection inconnue : ".implode(', ', $unknown)."\n\n");
    exit(1);
}

$roleId = 'cle-'.strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? 'sans-nom');
$roleId = trim($roleId, '-');

if ($app->dataStorage->findOne('system/api_keys', ['name' => $name])) {
    fwrite(STDERR, "\n  Une clé nommée « {$name} » existe déjà.\n\n");
    exit(1);
}

$now = time();

$role = $app->dataStorage->findOne('system/roles', ['appid' => $roleId]) ?? [
    'appid' => $roleId,
    'name' => $name,
    'info' => 'Rôle créé pour la clé « '.$name.' ».',
    'expressions' => [],
    '_created' => $now,
];

$role['permissions'] = $permissions;
$role['_modified'] = $now;

$app->dataStorage->save('system/roles', $role);

$token = 'API-'.bin2hex(random_bytes(20));

$key = [
    'key' => $token,
    'name' => $name,
    'role' => $roleId,
    'meta' => [],
    '_created' => $now,
    '_modified' => $now,
];

$app->dataStorage->save('system/api_keys', $key);

// Roles and keys are held in memory; without this the new key is refused.
$app->helper('acl')->cache();
$app->helper('api')->cache();

echo "\n  Clé « {$name} » créée.\n\n";
echo "  Clé  : {$token}\n";
echo "  Rôle : {$roleId}\n";
echo '  Droits : '.implode(', ', array_keys($permissions))."\n";
echo "\n  À noter maintenant : elle n'est plus affichée ensuite.\n\n";
