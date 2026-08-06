<?php

declare(strict_types=1);

use App\Application;
use App\Cache\PageCache;
use App\Cockpit\Client;
use App\Contact\ContactForm;
use App\Contact\SpamGuard;
use App\Content\Blocks;
use App\Content\Repository;
use App\Media\MediaUrls;
use App\Media\Picture;
use App\Theme\Colours;
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

// Disabled in development so an edit shows up on the next reload; in
// production the cache is what keeps the site fast on shared hosting.
// An unset — or empty — PAGE_CACHE follows APP_ENV.
$pageCache = $_ENV['PAGE_CACHE'] ?? '';

$cache = new PageCache(
    __DIR__.'/cache',
    $pageCache === '' ? $env === 'prod' : filter_var($pageCache, FILTER_VALIDATE_BOOL),
);

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

$twig->addExtension(new SiteExtension(new Picture($media)));
// Templates need it to link the home page at the root rather than at its slug.
$twig->addGlobal('accueilSlug', $homePageSlug);

// The contact form writes with a key of its own, allowed on that collection
// and nothing else. Without it, the form is simply not offered.
$writeKey = $_ENV['COCKPIT_WRITE_KEY'] ?? '';

$contactForm = new ContactForm(
    $writeKey === '' ? null : new Client($apiUrl, $writeKey),
    new SpamGuard("{$root}/var/contact", hash('sha256', $apiKey)),
);

$application = new Application(
    new Repository(new Client($apiUrl, $apiKey)),
    $twig,
    $media,
    new Blocks("{$root}/templates/blocs"),
    $contactForm,
    new Colours(__DIR__.'/assets/css/couleurs.css'),
    $siteUrl,
    $homePageSlug,
);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

$response = $application->handle(
    $requestUri,
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : [],
    (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
);

// Store before sending: the next visitor is served by the web server alone,
// without starting PHP at all.
$cache->store(
    parse_url($requestUri, PHP_URL_PATH) ?: '/',
    $response->body,
    $response->status,
    $_SERVER,
);

$response->send();
