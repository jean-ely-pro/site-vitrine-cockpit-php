# Site vitrine — Cockpit + PHP

Site vitrine autonome hébergé **entièrement chez le client** : une administration de contenu
et un site public rendu côté serveur, sur un hébergement mutualisé classique.

- **Administration** — Cockpit CMS (PHP + SQLite), servie depuis `/admin`.
- **Site public** — PHP 8.3 + Twig, rendu côté serveur : le contenu est présent dans le HTML
  de la première réponse, sans exécution de JavaScript.
- **Déploiement** — envoi de fichiers. Aucun runtime Node n'est requis en production.

## Prérequis

- PHP **8.3** ou plus, avec les extensions `pdo_sqlite`, `gd`, `curl`, `fileinfo`, `zip`
- Composer
- Apache avec `mod_rewrite` (en production)

Vérification :

```bash
php -r 'foreach (["pdo_sqlite","gd","curl","fileinfo","zip"] as $e) printf("%-10s %s\n", $e, extension_loaded($e) ? "ok" : "MANQUANT");'
```

## Installation en local

```bash
composer install                  # dépendances du site public
php bin/install-cockpit.php       # télécharge et installe Cockpit dans public/admin
cp .env.example .env              # puis ajuster si besoin
php bin/cockpit-init.php          # compte d'administration, clé de lecture, contenu de démonstration
```

`bin/cockpit-init.php` affiche **une seule fois** le mot de passe du compte `admin` et inscrit
la clé de lecture dans `.env`.

Deux serveurs, parce que le serveur intégré de PHP n'applique pas les règles `.htaccess` :

```bash
composer serve          # site public       → http://localhost:8080
composer serve-admin    # administration    → http://localhost:8090
```

En production, un seul hôte suffit : Apache sert le site et route `/admin` vers Cockpit.

## Structure du dépôt

```
bin/          scripts d'installation et d'initialisation
cockpit/      configuration Cockpit et modèle de contenu du projet (versionnés)
docs/         documentation technique
public/       racine web : point d'entrée, ressources, administration installée
src/          code du site public
templates/    gabarits Twig
var/          données d'exécution : base SQLite, caches (hors dépôt, hors racine web)
```

Cockpit n'est **pas versionné** : `bin/install-cockpit.php` télécharge l'archive officielle
d'une version épinglée et en vérifie l'empreinte SHA-256. La configuration et le modèle de
contenu vivent dans `cockpit/` et sont réinstallés à chaque mise à jour, donc jamais écrasés.

## Modèle de contenu

La structure est **figée** : le client remplit le contenu, il ne crée ni collection ni champ.

| Élément | Type | Contenu |
|---|---|---|
| `settings` | singleton | identité, couleurs, coordonnées, horaires, réseaux |
| `pages` | collection | titre, adresse, sections, référencement |
| `articles` | collection | titre, adresse, date, catégorie, résumé, image, texte |
| `menu` | singleton | entrées ordonnées, chacune pointant sur une page |

La publication n'est pas un champ : Cockpit la gère nativement sur chaque élément
(« Publié » / « Non publié » / « Archivé »). Le site public ne sert que les éléments publiés.

Pour modifier la structure, éditer `cockpit/models/*.model.php` puis relancer
`php bin/install-cockpit.php --force`.

## Adresses du site

| Adresse | Contenu |
|---|---|
| `/` | page dont le slug est `HOME_PAGE_SLUG` |
| `/{slug}` | une page |
| `/actualites` | liste des actualités, la plus récente en premier |
| `/actualites/{slug}` | une actualité |
| `/sitemap.xml`, `/robots.txt` | pour les moteurs de recherche |

`actualites` est **réservé** : l’administration refuse une page portant ce slug, en le disant.

## Édition par le client

Le compte `client` porte un rôle limité au contenu : identité, pages, menu, actualités et
images. Il ne peut ni modifier la structure, ni gérer les comptes, les rôles ou les clés.

Trois **modèles de page** sont fournis, laissés non publiés — le client les duplique. C'est la
duplication native de Cockpit : aucun mécanisme à maintenir, et les modèles évoluent comme
n'importe quelle page.

L'éditeur de texte ne propose que Titre 2, Titre 3, gras, italique, listes et liens. Un texte
collé depuis un traitement de texte est **corrigé à l'enregistrement** : les niveaux de titre
hors plage sont ramenés, quel que soit le chemin emprunté.

Tout est détaillé dans [docs/guide-client.md](docs/guide-client.md).

## Médias

Une image envoyée depuis l'administration est aussitôt déclinée en **copies allégées** — 480,
960 et 1440 px, en WebP — jamais plus larges que l'original. Le navigateur choisit celle qui
convient à la place dont il dispose ; le repli est la copie de 960 px, jamais l'original.

Les copies sont faites **à l'envoi**, pas au rendu, et leurs adresses sont portées par le média
lui-même : afficher une page n'exige aucun appel supplémentaire.

**La description d'une image est obligatoire** : l'enregistrement est refusé sans elle.

Détails dans [docs/medias.md](docs/medias.md) — point focal, alerte de poids, régénération.

## Sections de page

Une page est une suite de **sections**. Trois types sont fournis :

| Type | Rôle | Champs propres |
|---|---|---|
| `hero` | bandeau d'ouverture | accroche, image, bouton |
| `texte-image` | texte à côté d'une illustration | texte, image, position de l'image |
| `contact` | coordonnées reprises de l'identité | texte d'introduction, horaires |

### Ajouter un type de section

1. Créer `templates/blocs/mon-type.html.twig`. Le partial reçoit `bloc` (les valeurs
   saisies), `site` (l'identité), `titre`, `niveau` (1 ou 2, pour la balise de titre) et
   `premier` (vrai pour la première section de la page).
2. Ajouter `mon-type` à la liste du champ « Type de section » dans
   `cockpit/models/pages.model.php`, puis les champs qui lui sont propres, chacun avec une
   `condition` du genre `data.type === 'mon-type'` — c'est elle qui n'affiche ces champs que
   pour ce type.
3. `php bin/install-cockpit.php --force`

Rien d'autre à déclarer : un type existe dès qu'un partial porte son nom. Une section dont le
type n'a pas de partial n'est simplement pas affichée.

### Titres

La première section porte le `<h1>` quand c'est un bandeau — sinon le titre de la page est
affiché au-dessus des sections. Il y a donc toujours **un seul `<h1>`** par page, et les
titres de section sont des `<h2>`.

## Mise à jour de Cockpit

Chaque hébergement se met à jour séparément — il n'existe pas de vue centralisée. La procédure
est décrite dans [docs/cockpit-prerequis.md](docs/cockpit-prerequis.md).

## Cache de pages

Chaque page rendue est écrite en fichier statique dans `public/cache`. À la visite suivante,
**Apache sert ce fichier seul — PHP n'est pas démarré.** C'est ce qui rend le site tenable sur
un hébergement mutualisé, où démarrer PHP à chaque visite coûte cher.

L'en-tête `X-Page-Cache` dit qui a répondu : `hit` pour le serveur web, `miss` pour PHP.

```bash
curl -sI https://domaine.tld/services | grep -i x-page-cache
```

**Le cache est vidé dès qu'un contenu est enregistré dans l'administration** : un fichier
d'amorçage chargé par Cockpit s'en charge. La purge est totale — l'identité du site, le menu
et les titres de pages figurent sur toutes les pages, une purge partielle laisserait des
copies périmées. Chaque page est simplement rendue à nouveau à sa prochaine visite.

Ne sont jamais mis en cache : l'administration et son API, les réponses en erreur, et toute
adresse portant des paramètres.

### Purger à la main

Après un déploiement de gabarits ou d'une nouvelle feuille de style — dont l'adresse est déjà
inscrite dans les pages stockées :

```bash
php bin/purge-cache.php
```

### En développement

Le cache est **inactif** quand `APP_ENV=dev`, pour voir ses modifications immédiatement.
`PAGE_CACHE=true` dans `.env` permet de le tester en local.

## Sécurité

L'administration est le point sensible de cette installation. En résumé :

- **HTTPS forcé** partout, avec prise en compte des hébergements qui terminent le TLS en amont.
- **Double authentification** sur les comptes d'administration — native dans Cockpit, à activer
  compte par compte dès l'installation.
- **Politique de mot de passe** appliquée côté serveur, avec indicateur de force à la saisie.
- **Clés d'API porteuses d'un rôle** : celle du site est en lecture seule et ne peut rien
  écrire. Aucune clé d'écriture n'existe tant qu'aucun besoin ne la justifie.
- **En-têtes de sécurité** dont une politique de contenu stricte, cohérente avec un site qui ne
  charge aucune ressource tierce.

Tout est détaillé dans [docs/securite.md](docs/securite.md), avec les commandes de vérification
à passer sur chaque installation.

## Référencement

Chaque page porte son propre `<title>` et sa méta-description, et le site publie
`/sitemap.xml` et `/robots.txt`. L'établissement est décrit en JSON-LD `LocalBusiness` à
partir de l'identité, des coordonnées et des horaires.

**Les horaires sont saisis en texte libre** — « 9h – 12h, 14h – 18h30 » — et affichés tels
quels. Ils ne sont convertis en données structurées que lorsqu'ils se lisent sans ambiguïté ;
« sur rendez-vous » ou « 24h/24 » sont affichés mais laissés hors du JSON-LD. Publier des
horaires structurés faux serait pire que de n'en publier aucun.

## Bon à savoir

- **L'interface d'administration s'affiche en anglais.** Cockpit ne livre pas de fichiers de
  traduction ; la francisation reste à faire.
- **Un antivirus peut bloquer l'installation.** Certains bloquent l'écriture d'un `index.php`
  dans un dossier nommé `admin`. Le script d'installation s'arrête alors avec un message
  explicite : ajouter une exception sur le dossier du projet.

## Documentation

- [Ce que le client peut faire](docs/guide-client.md) — modèles de page, menu, actualités,
  référencement, limites de l'éditeur
- [Médias](docs/medias.md) — copies allégées, point focal, description obligatoire, poids
- [Sécurité de l'installation](docs/securite.md) — HTTPS, double authentification, mots de
  passe, clés d'API, en-têtes, vérifications à passer sur chaque installation
- [Prérequis et capacités de Cockpit](docs/cockpit-prerequis.md) — version retenue, rôles,
  double authentification, API, avis de sécurité, procédure de mise à jour
- [Développement local](docs/developpement-local.md) — ports, dépannage, réinitialisation

## Licence

Propriétaire — tous droits réservés.
