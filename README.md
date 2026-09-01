# Site vitrine — Cockpit + PHP

Site vitrine autonome hébergé **entièrement chez le client** : une administration de contenu
et un site public rendu côté serveur, sur un hébergement mutualisé classique.

- **Administration** — Cockpit CMS (PHP + SQLite), servie depuis `/admin`.
- **Site public** — PHP 8.3 + Twig, rendu côté serveur : le contenu est présent dans le HTML
  de la première réponse, sans exécution de JavaScript.
- **Déploiement** — envoi de fichiers. Aucun runtime Node n'est requis en production.

Ce fichier donne de quoi installer le projet et s'y retrouver. Tout le reste est dans
[docs/](docs/README.md), ordonné par situation.

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

Ports, dépannage et réinitialisation : [docs/developpement-local.md](docs/developpement-local.md).

## Structure du dépôt

```
bin/               scripts d'installation et d'initialisation
cockpit/           configuration Cockpit et modèle de contenu (versionnés)
docs/              documentation technique
public/            racine web : point d'entrée, ressources, médias, administration installée
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
correction du socle se récupère alors par fusion sans conflit sur la maquette — voir
[docs/guide-integration.md](docs/guide-integration.md).

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
| `/medias/…` | images envoyées depuis l'administration |
| `/sitemap.xml`, `/robots.txt` | pour les moteurs de recherche |

`actualites` est **réservé** : l'administration refuse une page portant ce slug, en le disant.

## Ce que fait le produit

| Sujet | En bref | Détails |
|---|---|---|
| Édition par le client | rôle limité au contenu, éditeur bridé à Titre 2 et Titre 3, modèles de page à dupliquer | [guide-client.md](docs/guide-client.md) |
| Sections de page | cinq types fournis ; `temoignages` sert de modèle commenté pour en créer un | [guide-integration.md](docs/guide-integration.md) |
| Médias | copies allégées fabriquées à l'envoi, description d'image obligatoire | [medias.md](docs/medias.md) |
| Formulaire de contact | poste sur le site, dépose dans l'administration, notifie par e-mail ; aucun service tiers | [formulaire-contact.md](docs/formulaire-contact.md) |
| Cache de pages | HTML statique servi par Apache sans démarrer PHP, purgé dès qu'un contenu est enregistré | [architecture.md](docs/architecture.md) |
| Référencement | titre, description et adresse canonique par page, JSON-LD `LocalBusiness`, plan du site | [architecture.md](docs/architecture.md) |
| Pages légales et couleurs | écrites par le site depuis l'identité ; une couleur sous 4,5:1 est refusée | [guide-client.md](docs/guide-client.md) |
| Sécurité | HTTPS forcé, double authentification, clés d'API porteuses d'un rôle, en-têtes stricts | [securite.md](docs/securite.md) |
| Mise en ligne et mise à jour | droits d'écriture, mise en service, mise à jour de Cockpit et de PHP | [installation-mutualise.md](docs/installation-mutualise.md) |

## Tests et vérification

```bash
composer test                                                  # 234 tests, sans réseau
php bin/verifier-accessibilite.php https://domaine-du-client.tld
```

Le premier lit le code, le second lit le HTML réellement servi — cache compris. Ils ne se
remplacent pas. Ce que chacun couvre : [docs/tests.md](docs/tests.md).

## Bon à savoir

- **L'interface d'administration s'affiche en anglais.** Cockpit ne livre pas de fichiers de
  traduction ; la francisation reste à faire.
- **Un antivirus peut bloquer l'installation.** Certains bloquent l'écriture d'un `index.php`
  dans un dossier nommé `admin`. Le script d'installation s'arrête alors avec un message
  explicite : ajouter une exception sur le dossier du projet.

## Versions

Le numéro installé est dans le fichier `VERSION`. Ce que chaque version apporte, et ce qu'elle
demande à un site déjà en service, est dans [CHANGELOG.md](CHANGELOG.md).

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
- [Tests et vérification](docs/tests.md) — ce que couvre la suite, ce que lit le contrôle en
  ligne

## Licence

Propriétaire — tous droits réservés.
