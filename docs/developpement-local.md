# Développement local

## Les deux serveurs

Le serveur intégré de PHP n'applique pas les règles `.htaccess`. En local, l'administration et
le site public tournent donc séparément :

| | Commande | Adresse |
|---|---|---|
| Site public | `composer serve` | <http://localhost:8080> |
| Administration | `composer serve-admin` | <http://localhost:8090> |

`.env` doit pointer `COCKPIT_API_URL` sur le port de l'administration.

En production, cette séparation disparaît : Apache sert le site depuis `public/` et `/admin`
est un simple sous-dossier, avec les règles de `public/.htaccess`.

### Si un port est déjà pris

Docker, WSL ou un autre service occupent parfois 8080 ou 8081. Pour le vérifier :

```powershell
Get-NetTCPConnection -LocalPort 8090 -State Listen
```

Changer alors le port dans `composer.json` (`serve`, `serve-admin`) **et** dans `.env`.

## Variables d'environnement

| Variable | Rôle |
|---|---|
| `APP_ENV` | `dev` : erreurs détaillées, gabarits recompilés à chaque requête. `prod` : l'inverse. |
| `COCKPIT_API_URL` | Adresse de l'API de contenu, terminée par `/api`. |
| `COCKPIT_API_KEY` | Clé de lecture, écrite par `bin/cockpit-init.php`. Elle n'autorise aucune écriture. |
| `SITE_URL` | Adresse publique du site. Sert au plan du site et aux données structurées, qui exigent des adresses complètes. |
| `MEDIA_BASE_URL` | Où sont servies les images. En développement l'administration a son propre port, d'où une adresse complète ; en production, `/admin/storage/uploads`. |
| `HOME_PAGE_SLUG` | Adresse de la page servie à la racine du site. |

## Le point d'entrée sert aussi de routeur en développement

Le serveur intégré de PHP répond 404 lui-même pour toute adresse qui ressemble à un fichier :
`/sitemap.xml` et `/robots.txt` n'atteindraient jamais le site. `public/index.php` lui sert
donc de routeur — il livre ce qui existe sur le disque et traite le reste, exactement ce que
fait Apache en production. D'où le `public/index.php` final dans `composer serve`.

## Réinitialiser complètement

```bash
rm -rf var/data public/admin        # efface la base, les médias et l'installation
php bin/install-cockpit.php
php bin/cockpit-init.php
```

`var/data` contient la base SQLite : **la supprimer efface tout le contenu saisi.**

## Modifier le modèle de contenu

1. Éditer `cockpit/models/settings.model.php` ou `pages.model.php`.
2. `php bin/install-cockpit.php --force` — les fichiers sont recopiés dans l'installation.
3. Recharger l'administration.

Le contenu déjà saisi n'est pas perdu : seule la définition des champs est remplacée. Renommer
un champ rend en revanche les valeurs existantes inaccessibles sous l'ancien nom.

## Dépannage

**Le site répond 503.** L'API n'est pas joignable ou la clé est refusée. Vérifier que
l'administration tourne, puis :

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H "api-key: LA_CLE" http://localhost:8090/api/content/item/settings
```

`200` attendu. `412` signale une clé inconnue, `401` un rôle sans droit de lecture.

**L'administration affiche « Something broke ».** Un dossier d'exécution manque sous
`public/admin/storage` (`cache`, `tmp/thumbs`, `uploads`). Relancer
`php bin/install-cockpit.php --force`, qui les recrée.

**Des fichiers disparaissent pendant l'installation.** Un antivirus supprime les scripts PHP
qui écrivent des fichiers, et les `index.php` placés dans un dossier `admin`. Ajouter une
exception sur le dossier du projet. Le script d'installation vérifie l'intégrité de
l'installation et s'arrête si des fichiers manquent.

**`composer install` refuse les certificats.** Un antivirus qui inspecte le trafic HTTPS
remplace les certificats par les siens ; sa racine est absente du magasin utilisé par PHP.
Désactiver l'inspection HTTPS ou ajouter la racine au fichier CA pointé par `curl.cainfo`.

## Vérifier le rendu comme le voit un moteur de recherche

```bash
curl -s localhost:8080/ | grep -o 'lang="fr"'
curl -s localhost:8080/ | grep -c '<h1'          # 1 attendu
curl -s localhost:8080/ | grep -o '<title>[^<]*</title>'
```

Le contenu éditorial doit apparaître dans cette sortie brute : c'est le seul HTML que voient
les robots qui n'exécutent pas JavaScript.
