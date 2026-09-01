# Architecture du site public

Ce document décrit le code de `src/` : par où passe une requête, ce que contient chaque
dossier, où intervenir selon le besoin, et pourquoi la structure est celle-là.

L'administration (Cockpit) n'est pas décrite ici : c'est un logiciel tiers, voir
[cockpit-prerequis.md](cockpit-prerequis.md).

## Le trajet d'une requête

Deux trajets. Le second est celui de la grande majorité des visites.

### Page déjà en cache — PHP ne démarre pas

```
navigateur → Apache → public/cache/services.html
```

`.htaccess` réécrit vers le fichier statique s'il existe. Aucun code du site n'est exécuté.

### Page absente du cache

```
navigateur
   │
   ▼
Apache ──────────────► public/index.php          assemble les objets, une fois
   │                        │
   │                        ▼
   │                   Application::handle()      lit l'adresse
   │                        │
   │                        ▼
   │                   Application::dispatch()    choisit qui répond
   │                        │
   │                        ▼
   │                   src/Controller/*Action     une classe par famille d'adresses
   │                     │            │
   │        ┌────────────┘            └───────────┐
   │        ▼                                     ▼
   │   Content\Repository                    View\ViewContext
   │        │                              (identité, menu, JSON-LD,
   │        ▼                               couleurs, formulaire)
   │   Cockpit\Client ──► API Cockpit            │
   │                                              ▼
   │                                        Twig ── templates-client/
   │                                             └─ templates/
   │                        │
   │                        ▼
   │                   Http\Response
   │                        │
   │                        ▼
   │                   Cache\PageCache::store()   écrit public/cache/*.html
   ▼                        │
navigateur ◄────────────────┘
```

L'écriture du cache a lieu **avant** l'envoi, dans `public/index.php`. La visite suivante
emprunte le premier trajet.

Ne sont **jamais** mis en cache : l'administration et son API, les réponses en erreur, et toute
adresse portant des paramètres. Une réponse en erreur stockée survivrait à la cause qui l'a
produite, et une adresse à paramètres répond autre chose selon ces paramètres. L'en-tête
`X-Page-Cache` dit qui a répondu — `hit` pour le serveur web, `miss` pour PHP.

## Rôle de chaque dossier de `src/`

L'organisation est **par sujet**, pas par couche technique. Le nom d'un dossier dit de quoi il
s'occupe, pas ce qu'il est.

| Dossier | S'occupe de | Dépend de |
|---|---|---|
| `Controller/` | répondre à une famille d'adresses | `Contact`, `Content`, `Http`, `Seo`, `View` |
| `View/` | ce que tout gabarit reçoit, fonctions Twig, couleurs | `Contact`, `Content`, `Media`, `Seo` |
| `Content/` | lire pages, actualités, identité ; associer une section à son partial | `Cockpit` |
| `Contact/` | recevoir un message, anti-spam, résultat de l'envoi | `Cockpit` |
| `Cockpit/` | parler à l'API : requêtes, erreurs, indisponibilité | rien |
| `Http/` | table des adresses (`Route`), réponse HTTP (`Response`) | rien |
| `Media/` | adresses d'images, `srcset`, dimensions | rien |
| `Seo/` | JSON-LD `LocalBusiness`, horaires, plan du site | rien |
| `Cache/` | écrire et purger les pages statiques | rien |
| `Audit/` | contrôler le HTML réellement servi — **hors du service d’une page** | rien |

Le tableau est trié : ce qui dépend le plus est en haut, ce qui ne dépend de rien en bas. Les
six derniers dossiers s'utilisent isolément et se testent sans rien monter autour.

`Audit/` est le seul dossier que le site n'ouvre jamais : ses classes ne servent qu'à
`bin/verifier-accessibilite.php`, avant une mise en ligne.

`Application.php` est à la racine de `src/` : il n'appartient à aucun sujet, il les relie.

Les dépendances vont dans un seul sens : `Controller` → `View`/`Content` → `Cockpit`. Aucun
dossier ne dépend de `Controller`.

## Où intervenir selon le besoin

| Besoin | Fichier à ouvrir |
|---|---|
| Ajouter un type de section | `templates-client/blocs/` sur un site, `templates/blocs/` dans le socle, puis `cockpit/models/pages.model.php` — voir [guide-integration.md](guide-integration.md) |
| Changer l'apparence | `templates-client/`, `public/assets/css/client.css` |
| Ajouter une adresse | `src/Http/Route.php`, puis un `Action` dans `src/Controller/` |
| Changer ce que reçoivent **tous** les gabarits | `src/View/ViewContext.php` |
| Ajouter une fonction Twig | `src/View/SiteExtension.php` |
| Modifier une requête à l'API | `src/Content/Repository.php` |
| Changer les données structurées | `src/Seo/LocalBusiness.php` |
| Changer ce qui est mis en cache | `src/Cache/PageCache.php` |
| Changer ce qui se passe à l'enregistrement | `cockpit/bootstrap.php` |
| Contraindre le client dans l'administration | `cockpit/addons/EditorGuards/` |

## Ajouter une adresse

Trois étapes, dans cet ordre :

1. Déclarer l'adresse dans `src/Http/Route.php` et l'ajouter à `RESERVED` — sans quoi le
   client pourrait créer une page du même nom, qui ne serait jamais servie.
2. Écrire `src/Controller/MonAction.php`. Une classe, une méthode `__invoke()` ou une méthode
   par adresse. Elle retourne un `Response`, ou `null` pour laisser répondre 404.
3. L'ajouter au `match` de `Application::dispatch()` et à la construction dans
   `public/index.php`.

`tests/Site/RoutageTest.php` vérifie que les adresses réservées du site et celles refusées par
l'administration ne divergent pas.

## Décisions structurantes

### Il n'y a pas de dossier `Service/`

Un dossier nommé d'après un motif technique accueille tout : au bout d'un an il contient
quinze classes sans rapport entre elles, et plus personne ne sait où chercher. Les dossiers
portent donc le nom du sujet traité — `Contact`, `Media`, `Seo`.

Une nouvelle classe va dans le dossier de son sujet. Si aucun ne convient, c'est un nouveau
sujet : créer un dossier qui le nomme.

### Il n'y a pas de dossier `Core/`

Même raison. « Ce qui est central » n'est pas un sujet : le dossier finit par contenir ce
qu'on n'a pas su classer. `Application.php` est seul à la racine de `src/` parce qu'il relie
les sujets sans en traiter aucun.

### Ce qui vérifie un site est séparé de ce qui le sert

`Audit/` réunit les classes qui ne tournent qu'avant une mise en ligne. Classées par sujet
elles étaient dispersées — l'accessibilité d'un côté, le référencement de l'autre — alors
qu'elles partagent le seul trait qui compte pour situer du code : elles ne s'exécutent jamais
quand un visiteur demande une page.

Une classe va dans `Audit/` si le site ne l'ouvre pas. Sinon elle va dans le dossier de son
sujet.

### L'accès à l'API passe par une interface

`Content\Repository` dépend de `Cockpit\ContentSource`, pas de `Cockpit\Client`. Les tests
fournissent leur propre implémentation : la suite s'exécute sans réseau et sans Cockpit
installé, en moins d'une seconde.

### `Application` ne fait que répartir

Elle lit l'adresse, appelle qui répond, et gère les deux cas où personne ne répond : 404, et
503 quand l'API est injoignable. Toute logique de page appartient à un `Action`.

Conséquence : `Application` reste lisible d'un seul coup d'œil, et une nouvelle adresse ne la
fait pas grossir.

### Les gabarits sont cherchés dans deux dossiers

`templates-client/` d'abord, `templates/` ensuite. Un gabarit du socle est **remplacé** sans
être modifié, donc une mise à jour du socle se récupère par `git merge` sans conflit sur la
maquette. Même principe pour `client.css`, chargé après `site.css`.

### Le cache est purgé en totalité

L'identité du site, le menu et les titres de pages figurent sur toutes les pages. Une purge
partielle laisserait des copies périmées ; chaque page est simplement rendue à nouveau à sa
prochaine visite.

### Cockpit n'est jamais modifié

Tout ajout à l'administration est un addon dans `cockpit/addons/`. Le cœur de Cockpit est
remplacé en entier à chaque mise à jour : une modification faite dedans serait perdue sans
avertissement.

## Deux pièges

### Cockpit utilise aussi le préfixe `App\`

`App\Exception\AppNotification` appartient à Cockpit, pas au site. Les deux ne se croisent
jamais — deux autochargements distincts — mais la lecture d'un fichier d'amorçage peut tromper.

### Les fichiers d'amorçage désignent les classes à la main

`cockpit/bootstrap.php` et les addons sont chargés par Cockpit, sans l'autochargement du site :
ils citent les classes par leur chemin et par leur nom. **Un renommage dans `src/` les casse en
silence** — l'administration ne démarre plus, ce qui ne se voit qu'en l'ouvrant.

`tests/GardeFous/AmorcageTest.php` vérifie que chaque classe et chaque chemin cités existent.
Le lancer après tout déplacement dans `src/` :

```bash
vendor/bin/phpunit --testsuite garde-fous
```
