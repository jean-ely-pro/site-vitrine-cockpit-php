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
| `pages` | collection | titre, adresse, contenu, référencement |

La publication n'est pas un champ : Cockpit la gère nativement sur chaque élément
(« Publié » / « Non publié » / « Archivé »). Le site public ne sert que les éléments publiés.

Pour modifier la structure, éditer `cockpit/models/*.model.php` puis relancer
`php bin/install-cockpit.php --force`.

## Mise à jour de Cockpit

Chaque hébergement se met à jour séparément — il n'existe pas de vue centralisée. La procédure
est décrite dans [docs/cockpit-prerequis.md](docs/cockpit-prerequis.md).

## Bon à savoir

- **L'interface d'administration s'affiche en anglais.** Cockpit ne livre pas de fichiers de
  traduction ; la francisation reste à faire.
- **Un antivirus peut bloquer l'installation.** Certains bloquent l'écriture d'un `index.php`
  dans un dossier nommé `admin`. Le script d'installation s'arrête alors avec un message
  explicite : ajouter une exception sur le dossier du projet.

## Documentation

- [Prérequis et capacités de Cockpit](docs/cockpit-prerequis.md) — version retenue, rôles,
  double authentification, API, avis de sécurité, procédure de mise à jour
- [Développement local](docs/developpement-local.md) — ports, dépannage, réinitialisation

## Licence

Propriétaire — tous droits réservés.
