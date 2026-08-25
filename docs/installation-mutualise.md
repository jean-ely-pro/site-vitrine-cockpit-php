# Installer le site chez le client

Compter une heure la première fois. Tout se fait par envoi de fichiers : il n'y a ni base de
données à créer chez l'hébergeur, ni ligne de commande indispensable.

## 1. Vérifier l'hébergement

Avant toute chose, dans le panneau de l'hébergeur :

| À vérifier | Attendu |
|---|---|
| Version de PHP | **8.3** ou plus |
| Extensions | `pdo_sqlite`, `gd`, `curl`, `fileinfo`, `zip` |
| Certificat HTTPS | actif sur le domaine |
| Racine du site | modifiable, si possible |

La plupart des hébergeurs permettent de choisir la version de PHP et d'activer des extensions
depuis leur panneau. Si `pdo_sqlite` manque et ne peut pas être activée, **cet hébergement ne
convient pas** : c'est la base de données du site.

## 2. Préparer l'envoi, en local

```bash
composer install --no-dev --optimize-autoloader
php bin/install-cockpit.php
```

Le dossier est alors complet. Ne pas lancer `bin/cockpit-init.php` : l'installation se fera
sur l'hébergement, pour que les mots de passe et les clés n'aient jamais existé ailleurs.

## 3. Envoyer les fichiers

Deux dispositions, selon ce que l'hébergeur autorise.

### Si la racine du site est modifiable — la bonne solution

Envoyer **tout le dossier** dans un répertoire du compte, par exemple `site/`, puis régler la
racine du site sur `site/public`.

```
site/
├── public/        ← racine du site
├── src/  templates/  vendor/  cockpit/  bin/
└── var/           ← créé tout seul, jamais accessible depuis le web
```

### Si la racine est imposée

Placer le **contenu** de `public/` dans le dossier public de l'hébergeur (`public_html`, `www`
ou `htdocs` selon les cas), et tout le reste **au-dessus**, hors du dossier public :

```
compte/
├── public_html/   ← contenu de public/ : index.php, .htaccess, assets/, admin/
├── src/  templates/  vendor/  cockpit/  bin/
└── var/
```

Dans ce cas, ouvrir `public_html/index.php` et remplacer `dirname(__DIR__)` par le chemin du
dossier qui contient `src/` — une seule ligne à changer.

### Ne pas envoyer

| Chemin | Pourquoi |
|---|---|
| `var/` | données d'exécution — recréé sur place |
| `.git/` | historique du dépôt |
| `docs/` | documentation |
| `.env` | secrets — celui de l'hébergement est écrit à l'étape 4 |
| `tests/`, `phpunit.xml` | ne s'exécutent jamais en production ; `--no-dev` a déjà retiré PHPUnit de `vendor/` |
| `src/Audit/` **et** `bin/verifier-accessibilite.php` | contrôle avant mise en ligne, lancé depuis un poste de développement |

`src/Audit/` et `bin/verifier-accessibilite.php` se retirent **ensemble** : le script ne se
charge pas sans le dossier. Envoyer l'un sans l'autre laisse sur l'hébergement une commande qui
s'arrête sur une erreur.

**Règle générale** : ce que `.gitignore` exclut n'a rien à faire sur l'hébergement — à deux
exceptions près, `vendor/` et `public/admin/`, produits par les commandes de l'étape 2 et donc
absents du dépôt mais indispensables en ligne.

### Droits d'écriture

Trois dossiers doivent être inscriptibles par le serveur :

```
var/                          base de données et caches
public/cache/                 pages mises en cache
public/admin/storage/         médias et copies allégées
```

En général `755` suffit ; certains hébergeurs demandent `775`.

## 4. Renseigner la configuration

Copier `.env.example` en `.env`, **à côté de `src/`** (jamais dans le dossier public), et
remplir :

```dotenv
APP_ENV=prod
SITE_URL=https://domaine-du-client.tld
COCKPIT_API_URL=https://domaine-du-client.tld/admin/api
MEDIA_BASE_URL=/admin/storage/uploads
HOME_PAGE_SLUG=accueil
PAGE_CACHE=
```

Laisser `COCKPIT_API_KEY` et `COCKPIT_WRITE_KEY` vides : elles seront écrites à l'étape
suivante.

## 5. Installer l'administration

Ouvrir **`https://domaine-du-client.tld/admin/install`**. Cockpit vérifie ses prérequis, crée
sa base et affiche un mot de passe. **Le noter tout de suite**, il n'est plus affiché ensuite.

Puis, si l'hébergeur donne accès à une ligne de commande :

```bash
php bin/cockpit-init.php
```

Ce script crée le rôle du site, le compte du client, les clés d'API — qu'il inscrit dans
`.env` — et le contenu de départ.

**Sans accès en ligne de commande**, tout se fait depuis l'administration : créer les deux
clés dans *Paramètres → API*, l'une en lecture sur `settings`, `pages`, `menu`, `articles` et
`legal`, l'autre en création sur `messages` seulement, puis les recopier dans `.env`. La
marche à suivre détaillée est dans [securite.md](securite.md).

## 6. Sécuriser

1. Se connecter à `/admin`, **activer la double authentification** sur le compte
   d'administration (menu du compte → *Account*).
2. Vérifier que le client a bien le rôle *Client* et non *Admin*.
3. Changer le mot de passe affiché à l'installation.

## 7. Renseigner le site

Dans l'administration : *Identité du site* (nom, coordonnées, horaires, couleurs), puis
*Mentions légales* — l'**hébergeur est obligatoire**, il est pré-rempli par « À renseigner ».

## 8. Purger et vérifier

```bash
php bin/purge-cache.php
```

Sans ligne de commande, enregistrer n'importe quel contenu depuis l'administration : cela vide
le cache.

Puis, depuis un poste quelconque :

```bash
php bin/verifier-accessibilite.php https://domaine-du-client.tld
```

Et les contrôles de sécurité :

```bash
curl -sI http://domaine-du-client.tld/            # 301 vers https
curl -sI https://domaine-du-client.tld/admin      # 302, jamais 200
curl -sI https://domaine-du-client.tld/ | grep -i content-security
curl -s https://domaine-du-client.tld/ | grep -c '<h1'      # 1
```

Enfin, **envoyer un message test** depuis l'administration : c'est la seule façon de savoir si
la notification par e-mail part réellement.

## Mettre à jour

### Cockpit

Chaque installation se met à jour séparément. Tenir la liste des sites livrés, avec pour chacun
la version de Cockpit installée et la date de la dernière mise à jour.

1. **Sauvegarder** `var/` et `public/admin/storage/` — base de données et médias.
2. En local, changer la version et l'empreinte dans `bin/install-cockpit.php`, puis
   `php bin/install-cockpit.php --force`.
3. Envoyer `public/admin/` en écrasant, **sauf `public/admin/storage/` et
   `public/admin/.env`**.
4. Ouvrir `/admin`, vérifier la connexion et l'affichage du contenu.
5. Purger le cache.
6. Noter la date et la version dans le suivi du client.

Surveiller les avis de sécurité publiés pour Cockpit — voir
[cockpit-prerequis.md](cockpit-prerequis.md), qui suit l'avis resté ouvert sur la version
retenue.

### PHP

Les hébergeurs proposent le changement de version depuis leur panneau. Après un changement :

1. Vérifier que les extensions requises sont toujours actives.
2. Ouvrir `/admin` et une page du site.
3. Purger le cache.

Ne pas passer à une version majeure sans avoir vérifié la compatibilité de Cockpit.

### Le site lui-même

Après un envoi de gabarits ou de feuille de style :

```bash
php bin/purge-cache.php
```

Les pages stockées contiennent les adresses des fichiers de style : sans purge, les visiteurs
continuent de recevoir l'ancienne mise en page.

## Récapitulatif des commandes

| Commande | Quand |
|---|---|
| `php bin/install-cockpit.php` | installation, mise à jour de Cockpit |
| `php bin/cockpit-init.php` | première installation |
| `php bin/purge-cache.php` | après un envoi de fichiers |
| `php bin/generer-variantes.php` | après un changement de largeurs d'images |
| `php bin/message-test.php` | vérifier que les e-mails partent |
| `php bin/verifier-accessibilite.php` | avant chaque mise en ligne — **depuis un poste de développement**, avec l'adresse du site |
