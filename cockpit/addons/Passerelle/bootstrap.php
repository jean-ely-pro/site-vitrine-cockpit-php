<?php

/**
 * A bridge between the admin and the public site, both ways.
 *
 * Admin → site: a « Voir le site » button in the header, and « Voir cette
 * page » while editing a page (assets/passerelle-admin.js).
 *
 * Site → admin: while someone is signed in, the site shows their avatar with
 * a green dot, linking back to the admin (public/assets/js/passerelle.js).
 * No public file names the admin: its address travels in a cookie set here,
 * in the signed-in person's browser only — visitors get neither cookie nor
 * avatar. Pages stay cacheable: the avatar is added in the browser.
 *
 * An addon rather than a patch, so updating Cockpit never undoes it.
 */

const PASSERELLE_COOKIE = 'passerelle';

/** The public site's address, from the project .env (SITE_URL); the admin's own host otherwise. */
$passerelleSite = function (): string {
    $env = (string) @file_get_contents(dirname(__DIR__, 4).'/.env');
    if (preg_match('/^SITE_URL=(.*)$/m', $env, $m) && trim($m[1]) !== '') {
        return rtrim(trim($m[1], " \t\"'"), '/');
    }

    return rtrim((string) $this->getSiteUrl(false), '/');
};

$this->on('before', function () {

    $route = (string) ($this->request->route ?? '');
    if (str_starts_with($route, '/api') || $route === '/check-session' || $route === '/app-event-stream') {
        return;
    }

    $user = $this->helper('auth')->getUser();
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $options = ['path' => '/', 'secure' => $https, 'httponly' => false, 'samesite' => 'Lax'];

    if (!$user) {
        if (isset($_COOKIE[PASSERELLE_COOKIE])) {
            setcookie(PASSERELLE_COOKIE, '', ['expires' => time() - 3600] + $options);
        }
        return;
    }

    $nom = trim((string) ($user['name'] ?? '')) ?: (string) ($user['user'] ?? '');

    // The account photo, when the Avatar addon has set one (read from the account, not the session).
    $compte = $this->dataStorage->findOne('system/users', ['_id' => $user['_id']]) ?? [];
    $photo = str_starts_with((string) ($compte['avatar'] ?? ''), '/avatars/')
        ? rtrim((string) $this->fileStorage->getURL('uploads://'), '/').$compte['avatar']
        : null;

    $valeur = rtrim(strtr(base64_encode((string) json_encode([
        'nom' => $nom,
        'photo' => $photo,
        // On Windows, dirname() in getSiteUrl() may end the address with a backslash.
        'admin' => rtrim(str_replace('\\', '/', (string) $this->getSiteUrl(true)), '/').'/',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

    // A session cookie: it goes when the browser closes, and on the next admin page after signing out.
    if (($_COOKIE[PASSERELLE_COOKIE] ?? '') !== $valeur) {
        setcookie(PASSERELLE_COOKIE, $valeur, $options);
    }
});

// For the site's avatar (public/assets/js/passerelle.js): is someone still signed in? Asked here rather than
// at Cockpit's /check-session so that the answer also says this addon is active: once it is disabled
// (Modules addon), this address no longer exists, and the site removes the avatar and its cookie.
// Same rules as /check-session: inactivity ends the session, and asking does not prolong it.
$this->on('app.admin.request', function (Lime\Request $request) use ($passerelleSite) {

    if ($request->route !== '/passerelle/etat') {
        return;
    }

    $connecte = (bool) $this->helper('auth')->getUser();
    $debut = $this->helper('session')->read('app.session.start', 0);

    if ($connecte && $debut && ($debut + $this->retrieve('session.lifetime', 5400) < time())) {
        $this->helper('auth')->logout();
        $connecte = false;
    }

    $this->bind('/passerelle/etat', function () use ($connecte, $passerelleSite) {
        $this->helper('session')->close();
        $this->response->mime = 'json';

        // The site may ask from another origin (in development: :8080 for the site, :8090 for the admin).
        // Only the site's own address is answered, and the answer is a yes or no, nothing more.
        $site = parse_url($passerelleSite());
        $origine = isset($site['scheme'], $site['host']) ? $site['scheme'].'://'.$site['host'].(isset($site['port']) ? ':'.$site['port'] : '') : '';
        if ($origine !== '' && ($_SERVER['HTTP_ORIGIN'] ?? '') === $origine) {
            $this->response->headers['Access-Control-Allow-Origin'] = $origine;
            $this->response->headers['Access-Control-Allow-Credentials'] = 'true';
            $this->response->headers['Vary'] = 'Origin';
        }

        return ['connecte' => $connecte];
    });

    // Cockpit's own handler would record activity and keep the session alive: not for a background check.
    return false;
}, 1001);

// The site's address and the page being edited, for the admin-side buttons.
$this->bind('/passerelle/infos', function () use ($passerelleSite) {

    $this->response->mime = 'json';

    $infos = ['site' => $passerelleSite(), 'page' => null];
    $id = (string) $this->param('page', '');

    if ($id !== '' && $this->helper('acl')->isAllowed('content/pages/read')) {
        $page = $this->module('content')->item('pages', ['_id' => $id]);
        if ($page && !empty($page['slug'])) {
            $accueil = (string) (getenv('HOME_PAGE_SLUG') ?: 'accueil');
            $infos['page'] = $page['slug'] === $accueil ? '/' : '/'.$page['slug'];
        }
    }

    return $infos;
});

$this->on('app.layout.assets', function (&$assets, $context) {

    if ($context === 'app:footer' && $this->helper('auth')->getUser()) {
        $assets[] = ['src' => 'passerelle:assets/passerelle-admin.js', 'type' => 'module', 'position' => 'footer'];
    }
});
