<?php

declare(strict_types=1);

use App\Application;
use App\Cockpit\Client;
use App\Content\Blocks;
use App\Content\Repository;
use App\Media\MediaUrls;
use App\Twig\SiteExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$root = dirname(__DIR__);

// PHP's built-in server does not read .htaccess and answers 404 by itself for
// any address that looks like a file, so /sitemap.xml would never arrive here.
// Used as its router, this file serves what exists on disk and handles the
// rest — which is what Apache does in production.
if (PHP_SAPI === 'cli-server') {

    $requested = __DIR__.(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    if ($requested !== __FILE__ && is_file($requested)) {
        return false;
    }
}

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

// Absolute addresses are needed by the sitemap and the structured data. When
// nothing is configured, the address of the current request is good enough.
$siteUrl = $_ENV['SITE_URL'] ?? '';

if ($siteUrl === '') {
    $scheme = ($_SERVER['HTTPS'] ?? '') !== '' ? 'https' : 'http';
    $siteUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
}

$homePageSlug = $_ENV['HOME_PAGE_SLUG'] ?? 'accueil';

$media = new MediaUrls(
    $_ENV['MEDIA_BASE_URL'] ?? '/admin/storage/uploads',
    $siteUrl,
);

$twig = new Environment(new FilesystemLoader("{$root}/templates"), [
    // Compiled templates live outside the web root, next to the rest of /var.
    'cache' => $env === 'prod' ? "{$root}/var/cache/twig" : false,
    'debug' => $env !== 'prod',
    'strict_variables' => false,
    'autoescape' => 'html',
]);

$twig->addExtension(new SiteExtension($media));
// Templates need it to link the home page at the root rather than at its slug.
$twig->addGlobal('accueilSlug', $homePageSlug);

$application = new Application(
    new Repository(new Client($apiUrl, $apiKey)),
    $twig,
    $media,
    new Blocks("{$root}/templates/blocs"),
    $siteUrl,
    $homePageSlug,
);

$application->handle($_SERVER['REQUEST_URI'] ?? '/')->send();
