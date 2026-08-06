<?php

declare(strict_types=1);

namespace Tests\Site;

use App\Cache\PageCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The cache decides what a visitor is served without PHP running. Anything it
 * stores by mistake — an error page, a draft, a path escaping its folder —
 * would be served to everyone until the next publication.
 */
final class PageCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/cache-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            (new PageCache($this->directory))->clear();
            @rmdir($this->directory);
        }
    }

    #[Test]
    #[DataProvider('adressesValides')]
    public function une_adresse_de_page_donne_un_fichier(string $route, string $attendu): void
    {
        $this->assertSame($attendu, (new PageCache($this->directory))->fileFor($route));
    }

    /** @return array<string, array{string, string}> */
    public static function adressesValides(): array
    {
        return [
            'la racine' => ['/', 'index.html'],
            'une page' => ['/services', 'services.html'],
            'un slug composé' => ['/nos-prestations', 'nos-prestations.html'],
            'une actualité' => ['/actualites/portes-ouvertes', 'actualites/portes-ouvertes.html'],
            'le plan du site' => ['/sitemap.xml', 'sitemap.xml'],
            'le fichier d’indexation' => ['/robots.txt', 'robots.txt'],
        ];
    }

    #[Test]
    #[DataProvider('adressesRefusees')]
    public function une_adresse_inattendue_nest_jamais_stockee(string $route): void
    {
        $this->assertNull(
            (new PageCache($this->directory))->fileFor($route),
            "L'adresse {$route} ne doit correspondre à aucun fichier",
        );
    }

    /** @return array<string, array{string}> */
    public static function adressesRefusees(): array
    {
        return [
            'une remontée de dossier' => ['/../.env'],
            'une remontée déguisée' => ['/actualites/../../secret'],
            'des majuscules' => ['/Services'],
            'trois niveaux' => ['/a/b/c'],
            'un caractère interdit' => ['/page_privee'],
            'une extension inattendue' => ['/config.php'],
        ];
    }

    #[Test]
    public function seules_les_reponses_reussies_sont_stockees(): void
    {
        $cache = new PageCache($this->directory);

        $cache->store('/services', 'contenu', 404, ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => '']);
        $cache->store('/services', 'contenu', 503, ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => '']);

        $this->assertFileDoesNotExist("{$this->directory}/services.html", 'Une page d’erreur ne doit jamais être servie ensuite');
    }

    #[Test]
    public function une_adresse_avec_parametres_nest_pas_stockee(): void
    {
        $cache = new PageCache($this->directory);
        $cache->store('/services', 'contenu', 200, ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'message=envoye']);

        $this->assertFileDoesNotExist("{$this->directory}/services.html");
    }

    #[Test]
    public function un_envoi_de_formulaire_nest_pas_stocke(): void
    {
        $cache = new PageCache($this->directory);
        $cache->store('/services', 'contenu', 200, ['REQUEST_METHOD' => 'POST', 'QUERY_STRING' => '']);

        $this->assertFileDoesNotExist("{$this->directory}/services.html");
    }

    #[Test]
    public function le_cache_desactive_nécrit_rien(): void
    {
        $cache = new PageCache($this->directory, false);
        $cache->store('/services', 'contenu', 200, ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => '']);

        $this->assertFileDoesNotExist("{$this->directory}/services.html");
    }

    #[Test]
    public function la_purge_vide_aussi_les_sous_dossiers(): void
    {
        $cache = new PageCache($this->directory);
        $serveur = ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => ''];

        $cache->store('/', 'accueil', 200, $serveur);
        $cache->store('/actualites/une-nouvelle', 'actualité', 200, $serveur);

        $this->assertFileExists("{$this->directory}/actualites/une-nouvelle.html");

        $cache->clear();

        $this->assertFileDoesNotExist("{$this->directory}/index.html");
        $this->assertFileDoesNotExist(
            "{$this->directory}/actualites/une-nouvelle.html",
            'Une actualité modifiée resterait servie depuis le cache',
        );
    }
}
