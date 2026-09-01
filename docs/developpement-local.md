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
| `MEDIA_BASE_URL` | Où sont servies les images — `public/medias/`, sous la racine du site. En développement, une adresse complète sur le port du **site** (8080), pour que l'administration, servie sur 8090, affiche ses aperçus ; en production, `/medias`. |
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

## Vérifier le cache de pages

Le serveur intégré de PHP ne lit pas `.htaccess` : il ne peut donc pas servir le cache. Il le
**produit** (les fichiers apparaissent dans `public/cache`), mais c'est Apache qui le **sert**.
Pour vérifier la chaîne complète en local, il faut un Apache pointant sur `public/`, avec
`AllowOverride All` et `mod_rewrite`, `mod_headers`, `mod_expires` chargés.

```bash
# 1. produire le cache (PAGE_CACHE=true dans .env)
curl -s -o /dev/null http://localhost:8080/services
ls public/cache/

# 2. le faire servir par Apache : « hit » et aucun X-Powered-By
curl -sI http://localhost/services | grep -iE 'x-page-cache|x-powered-by'
```

Le cache fige la page : modifier le contenu directement en base ne change rien à ce qui est
servi. Enregistrer depuis l'administration vide le cache, et la page suivante est rendue à
nouveau.

## Tests

```bash
composer test                       # tout
vendor/bin/phpunit --testsuite site # ce que reçoit le visiteur
vendor/bin/phpunit --testsuite garde-fous  # ce qui protège l'administration
```

Ils s'exécutent sans serveur, sans Cockpit et sans réseau : moins d'une seconde. Ce sont les
règles du produit qui sont vérifiées, pas l'intégration — celle-ci se contrôle avec
`bin/verifier-accessibilite.php`, qui lit le site réellement servi.

**À lancer avant chaque envoi de fichiers**, et après toute modification dans `src/` ou
`cockpit/addons/`.

### Ajouter un test

Un garde-fou sans test finit par disparaître à la faveur d'une modification. La règle simple :
tout ce qui protège le visiteur ou empêche le client de casser son site doit avoir un test qui
échoue si on le retire.

Les tests portent des noms de phrases françaises — `une_couleur_trop_pale_est_ecartee` — pour
que la liste des échecs se lise comme un constat, pas comme une trace technique.

## Les e-mails en développement

Aucune notification n'est envoyée tant que l'identité du site porte une adresse de
démonstration (`@example.test` et les autres domaines réservés aux tests). Le message est
enregistré, seule la notification est retenue.

**À vérifier avant de renseigner une vraie adresse en local** : sur beaucoup de postes, l'envoi
de courrier de PHP est relié à un vrai compte SMTP — sur Laragon, `bin/sendmail/sendmail.ini`
— souvent avec un expéditeur imposé. Un message adressé à un domaine inexistant revient alors
dans cette boîte. Une série de tests suffit à y déverser des dizaines de messages.

```bash
# ce que PHP utilise pour envoyer
php -i | grep -i "sendmail_path\|SMTP"
```

## Dépannage

**Le site répond 503.** L'API n'est pas joignable ou la clé est refusée. Vérifier que
l'administration tourne, puis :

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H "api-key: LA_CLE" http://localhost:8090/api/content/item/settings
```

`200` attendu. `412` signale une clé inconnue, `401` un rôle sans droit de lecture.

**L'administration affiche « Something broke ».** Un dossier d'exécution manque : `cache` ou
`tmp/thumbs` sous `public/admin/storage`, ou `public/medias`. Relancer
`php bin/install-cockpit.php --force`, qui les recrée.

`public/medias` est le plus sensible des trois : Cockpit le cherche sur le disque et abandonne
s'il ne le trouve pas.

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
