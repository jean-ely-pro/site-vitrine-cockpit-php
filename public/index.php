<?php

declare(strict_types=1);

use App\Application;
use App\Cockpit\Client;
use App\Content\Repository;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$root = dirname(__DIR__);

if (!is_file("{$root}/vendor/autoload.php")) {
    http_response_code(500);
    exit("Les dépendances ne sont pas installées. Lancer : composer install\n");
}

require "{$root}/vendor/autoload.php";

Dotenv\Dotenv::createImmutable($root)->safeLoad();

$env = $_ENV['APP_ENV'] ?? 'prod';
$apiUrl = $_ENV['COCKPIT_API_URL'] ?? '';
$apiKey = $_ENV['COCKPIT_API_KEY'] ?? '';

if ($apiUrl === '' || $apiKey === '') {
    http_response_code(500);
    exit("Configuration absente : renseigner COCKPIT_API_URL et COCKPIT_API_KEY dans .env.\n");
}

$twig = new Environment(new FilesystemLoader("{$root}/templates"), [
    // Compiled templates live outside the web root, next to the rest of /var.
    'cache' => $env === 'prod' ? "{$root}/var/cache/twig" : false,
    'debug' => $env !== 'prod',
    'strict_variables' => false,
    'autoescape' => 'html',
]);

$application = new Application(
    new Repository(new Client($apiUrl, $apiKey)),
    $twig,
    $_ENV['HOME_PAGE_SLUG'] ?? 'accueil',
);

$application->handle($_SERVER['REQUEST_URI'] ?? '/')->send();
