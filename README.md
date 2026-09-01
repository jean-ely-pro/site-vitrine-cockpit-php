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
bin/               scripts d'installation et d'initialisation
cockpit/           configuration Cockpit et modèle de contenu (versionnés)
docs/              documentation technique
public/            racine web : point d'entrée, ressources, administration installée
src/               code du site public
templates/         gabarits livrés avec le socle
templates-client/  gabarits propres à ce site — prioritaires sur les précédents
tests/             suite de tests
var/               données d'exécution : base SQLite, caches (hors dépôt, hors racine web)
```

Le trajet d'une requête, le rôle de chaque dossier de `src/` et les décisions qui ont fixé
cette structure sont décrits dans [docs/architecture.md](docs/architecture.md).

**Socle et personnalisation sont séparés.** Un gabarit placé dans `templates-client/` remplace
celui de `templates/` sans le modifier, et `client.css` est chargé après `site.css`. Une
correction du socle se récupère alors par `git merge socle/main` sans conflit sur la
maquette — voir [docs/guide-integration.md](docs/guide-integration.md).

Cockpit n'est **pas versionné** : `bin/install-cockpit.php` télécharge l'archive officielle
d'une version épinglée et en vérifie l'empreinte SHA-256. La configuration et le modèle de
contenu vivent dans `cockpit/` et sont réinstallés à chaque mise à jour, donc jamais écrasés.

## Modèle de contenu

La structure est **figée** : le client remplit le contenu, il ne crée ni collection ni champ.

| Élément | Type | Contenu |
|---|---|---|
| `settings` | singleton | identité, couleurs, coordonnées, horaires, réseaux, image de partage |
| `pages` | collection | titre, adresse, sections, référencement |
| `articles` | collection | titre, adresse, date, catégorie, résumé, image, texte |
| `menu` | singleton | entrées ordonnées, chacune pointant sur une page |
| `legal` | singleton | éditeur, hébergeur, compléments aux pages légales |
| `messages` | collection | messages reçus par le formulaire de contact |

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
| `/contact` | réception du formulaire de contact |
| `/mentions-legales`, `/confidentialite` | écrites par le site depuis l'identité |
| `/sitemap.xml`, `/robots.txt` | pour les moteurs de recherche |

`actualites` est **réservé** : l’administration refuse une page portant ce slug, en le disant.

## Édition par le client

Le compte `client` porte un rôle limité au contenu : identité, pages, menu, actualités et
images. Il ne peut ni modifier la structure, ni gérer les comptes, les rôles ou les clés.

Trois **modèles de page** sont fournis, laissés non publiés — le client les duplique avec la
fonction de duplication de Cockpit.

L'éditeur de texte ne propose que Titre 2, Titre 3, gras, italique, listes et liens. Un texte
collé depuis un traitement de texte est **corrigé à l'enregistrement** : les niveaux de titre
hors plage sont ramenés, quel que soit le chemin emprunté.

Tout est détaillé dans [docs/guide-client.md](docs/guide-client.md).

## Formulaire de contact

Le formulaire poste **sur le site lui-même** et dépose le message dans l'administration, avec
une notification par e-mail. Aucun service tiers n'intervient : ni captcha, ni script distant.

Trois contrôles le protègent — un champ invisible, le temps passé à le remplir, une limite de
cinq messages par heure et par adresse. La case de consentement **n'est jamais cochée d'avance**
et un lien vers la politique de confidentialité est obligatoire.

Le point qui échoue en silence est l'e-mail. Pour le vérifier :

```bash
php bin/message-test.php     # ou le bouton « Envoyer un message test » dans l'administration
```

Détails dans [docs/formulaire-contact.md](docs/formulaire-contact.md).

## Médias

Une image envoyée depuis l'administration est aussitôt déclinée en **copies allégées** — 480,
960 et 1440 px, en WebP — jamais plus larges que l'original. Le navigateur choisit celle qui
convient à la place dont il dispose ; le repli est la copie de 960 px, jamais l'original.

Les copies sont faites **à l'envoi**, pas au rendu, et leurs adresses sont portées par le média
lui-même : afficher une page n'exige aucun appel supplémentaire.

**La description d'une image est obligatoire** : l'enregistrement est refusé sans elle.

Détails dans [docs/medias.md](docs/medias.md) — point focal, alerte de poids, régénération.

## Sections de page

Une page est une suite de **sections**. Cinq types sont fournis :

| Type | Rôle | Champs propres |
|---|---|---|
| `hero` | bandeau d'ouverture | accroche, image, bouton |
| `texte-image` | texte à côté d'une illustration | texte, image, position de l'image |
| `contact` | coordonnées reprises de l'identité | texte d'introduction, horaires |
| `formulaire` | formulaire de contact | texte d'introduction, page de confidentialité |
| `temoignages` | liste de témoignages | introduction, citations, portraits |

`temoignages` sert de **modèle commenté** pour créer un type de section : partial, champs
Cockpit et styles, avec les règles à respecter. Voir
[docs/guide-integration.md](docs/guide-integration.md).

### Ajouter un type de section

1. Créer le partial. Sur le site d'un client : `templates-client/blocs/mon-type.html.twig`.
   Dans le socle, pour un type livré à tous les sites : `templates/blocs/mon-type.html.twig`.
   Le partial reçoit `bloc` (les valeurs saisies), `site` (l'identité), `titre`, `niveau`
   (1 ou 2, pour la balise de titre) et `premier` (vrai pour la première section de la page).
2. Ajouter `mon-type` à la liste du champ « Type de section » dans
   `cockpit/models/pages.model.php`, puis les champs qui lui sont propres, chacun avec une
   `condition` du genre `data.type === 'mon-type'` — c'est elle qui n'affiche ces champs que
   pour ce type.
3. `php bin/install-cockpit.php --force`

**Les deux premières étapes vont ensemble.** Le nom du fichier est le nom du type, et
`composer test` refuse qu'ils se séparent : un type proposé au client sans partial ajoute à sa
page une section qui n'apparaît pas, un partial sans option ne peut être choisi par personne.

Marche à suivre détaillée, contrat du partial, règles et pièges :
[docs/guide-integration.md](docs/guide-integration.md).

### Titres

La première section porte le `<h1>` quand c'est un bandeau — sinon le titre de la page est
affiché au-dessus des sections. Il y a donc toujours **un seul `<h1>`** par page, et les
titres de section sont des `<h2>`.

## Mise en ligne et mise à jour

L'installation sur un hébergement mutualisé, les droits d'écriture, la mise en service et les
procédures de mise à jour de Cockpit et de PHP sont décrites dans
[docs/installation-mutualise.md](docs/installation-mutualise.md).

Chaque installation se met à jour séparément. Tenir la liste des sites livrés, avec pour chacun
la version de Cockpit installée et la date de la dernière mise à jour.

## Cache de pages

Chaque page rendue est écrite en fichier statique dans `public/cache`. À la visite suivante,
**Apache sert ce fichier seul — PHP n'est pas démarré.**

L'en-tête `X-Page-Cache` dit qui a répondu : `hit` pour le serveur web, `miss` pour PHP.

```bash
curl -sI https://domaine.tld/services | grep -i x-page-cache
```

**Le cache est vidé dès qu'un contenu est enregistré dans l'administration** : un fichier
d'amorçage chargé par Cockpit s'en charge. La purge est totale ; chaque page est rendue à
nouveau à sa prochaine visite.

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

## Pages légales et couleurs

Les **mentions légales** et la **politique de confidentialité** sont écrites par le site
lui-même, à partir de l'identité et du singleton *Mentions légales* : le SIRET, l'adresse et
l'hébergeur ne sont saisis qu'une fois. Le client complète ce qui lui est propre, sans
recopier.

Les **couleurs** saisies dans l'identité sont appliquées au site — mais seulement si elles
atteignent le contraste exigé de 4,5:1. En dessous, le site garde sa couleur par défaut plutôt
que de devenir illisible, et l'administration le signale au moment de la saisie.

## Tests

```bash
composer test
```

229 tests couvrent les garde-fous du produit : ce qui décide de ce qu'un visiteur reçoit, et
ce qui empêche le site d'être cassé depuis l'administration.

| Ce qui est protégé | Exemples |
|---|---|
| Cache de pages | jamais une page d'erreur, jamais une adresse avec paramètres, aucune remontée de dossier |
| Formulaire de contact | consentement jamais supposé, retour toujours sur le site, anti-spam, limite par adresse |
| Couleurs | une couleur sous 4,5:1 n'atteint jamais le site |
| Référencement | horaires ambigus laissés de côté, aucun champ vide publié |
| Aperçu partagé | adresse revendiquée absolue ou absente, jamais fausse ; image en adresse complète |
| Site en ligne | l’adresse revendiquée est celle où la page répond — un SITE_URL erroné est signalé |
| Accessibilité | chaque défaut détectable est vérifié sur une page fautive |
| Mots de passe | longueur, variété, mots courants, nom du compte |
| Niveaux de titre | corrigés à l'enregistrement, sections imbriquées comprises |
| Descriptions d’images | exigées dès qu’une image est posée, jamais sans image |
| Brouillons | jamais demandés au service de contenu : seul l’état publié l’est |
| Amorçage de l’administration | les classes et chemins cités par les addons existent bien |
| Modèles de l’administration | types de champ réellement enregistrés, libellés de listes interpolés |
| Types de section | chaque type proposé au client a son gabarit, et réciproquement |

Les tests ne touchent pas au réseau et ne démarrent pas Cockpit : ils s'exécutent en moins
d'une seconde. Un seul lit les fichiers de l'administration installée, pour confronter les
types de champ aux composants que Cockpit enregistre ; il est ignoré tant que
`bin/install-cockpit.php` n'a pas tourné. Ils ne remplacent pas la vérification ci-dessous, qui
lit le site réel.

## Vérifier une mise en ligne

```bash
php bin/verifier-accessibilite.php https://domaine-du-client.tld
```

Le script lit **le HTML réellement servi** — cache compris — sur toutes les adresses du plan du
site : langue, titre unique, hiérarchie des titres, descriptions d'images, dimensions,
intitulés de formulaire, ressources tierces, transparence sur du texte et contraste des
couleurs. Aucune dépendance à installer.

Il confronte aussi **l'adresse que chaque page revendique** à celle où elle vient d'être lue.
C'est le seul contrôle qui attrape un `SITE_URL` erroné : la page se rend correctement, mais
annonce aux moteurs et aux réseaux une adresse qui n'est pas la sienne.

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

Chaque page porte son propre `<title>`, sa méta-description et **l'adresse qu'elle revendique**
(`rel="canonical"`), et le site publie `/sitemap.xml` et `/robots.txt`. L'établissement est
décrit en JSON-LD `LocalBusiness` à partir de l'identité, des coordonnées et des horaires.

**Un lien partagé affiche un aperçu maîtrisé.** Les balises Open Graph et la carte Twitter
reprennent le titre et la description de la page — jamais une seconde version qui dériverait —
et une image en adresse complète : l'*Image de partage* de l'identité du site, à défaut l'image
de la page, à défaut le logo. Rien n'est revendiqué quand `SITE_URL` est absente ou ne
ressemble pas à une adresse : un canonique faux envoie moteurs et réseaux ailleurs.

**Les horaires sont saisis en texte libre** — « 9h – 12h, 14h – 18h30 » — et affichés tels
quels. Ils ne sont convertis en données structurées que lorsqu'ils se lisent sans ambiguïté ;
« sur rendez-vous » ou « 24h/24 » sont affichés mais laissés hors du JSON-LD.

## Bon à savoir

- **L'interface d'administration s'affiche en anglais.** Cockpit ne livre pas de fichiers de
  traduction ; la francisation reste à faire.
- **Un antivirus peut bloquer l'installation.** Certains bloquent l'écriture d'un `index.php`
  dans un dossier nommé `admin`. Le script d'installation s'arrête alors avec un message
  explicite : ajouter une exception sur le dossier du projet.

## Documentation

Le sommaire ordonné par situation est dans [docs/README.md](docs/README.md).

- [Architecture du site public](docs/architecture.md) — trajet d'une requête, rôle de chaque
  dossier, où intervenir, décisions structurantes
- [Journal des versions](CHANGELOG.md) — ce que chaque version apporte, et ce qu'elle demande
  aux sites déjà installés
- [Créer un site et le tenir à jour](docs/mise-a-jour-socle.md) — créer le dépôt depuis le
  socle, récupérer les corrections par fusion
- [Intégrer une maquette](docs/guide-integration.md) — pour le développeur : types de section,
  contrat du partial, images, CSS, vérifications, pièges
- [Ce que le client peut faire](docs/guide-client.md) — modèles de page, menu, actualités,
  référencement, limites de l'éditeur
- [Médias](docs/medias.md) — copies allégées, point focal, description obligatoire, poids
- [Formulaire de contact](docs/formulaire-contact.md) — anti-spam, consentement, clés, e-mail
- [Installation sur mutualisé](docs/installation-mutualise.md) — envoi des fichiers, mise en
  service, mise à jour de Cockpit et de PHP
- [Sécurité de l'installation](docs/securite.md) — HTTPS, double authentification, mots de
  passe, clés d'API, en-têtes, vérifications à passer sur chaque installation
- [Prérequis et capacités de Cockpit](docs/cockpit-prerequis.md) — version retenue, rôles,
  double authentification, API, avis de sécurité, procédure de mise à jour
- [Développement local](docs/developpement-local.md) — ports, dépannage, réinitialisation

## Licence

Propriétaire — tous droits réservés.
