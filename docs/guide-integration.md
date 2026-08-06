# Intégrer une maquette

Pour un développeur qui reçoit une maquette et doit produire le site d'un client.

## Principe

Une installation = un client. Chaque site a son dépôt, ses gabarits, sa feuille de style. Les
couleurs et le contenu viennent de l'administration, pas du code.

Ce qui se code : les gabarits Twig, le CSS, les types de section.
Ce qui ne se code pas : textes, images, coordonnées, horaires, couleurs, menu.

## La règle

> **Tout ce que vous créez va dans `templates-client/` et `client.css`.
> Vous ne modifiez jamais `templates/` ni `site.css`.**

C'est ce qui permet de récupérer une correction du socle sans conflit : les fichiers que vous
touchez ne sont pas ceux qu'une mise à jour modifie.

| Vous voulez | Où |
|---|---|
| Ajouter un type de section | `templates-client/blocs/mon-type.html.twig` |
| Styler quoi que ce soit | `public/assets/css/client.css` |
| Refaire l'en-tête, le pied de page, une page | copier le fichier de `templates/` vers `templates-client/`, **même chemin**, et modifier la copie |

Un fichier présent dans `templates-client/` remplace celui de `templates/` sans que l'original
soit touché. `client.css` est chargé après `site.css`, donc il l'emporte.

Seule exception : `cockpit/models/pages.model.php`, qui décrit le modèle éditorial du site. Il
se modifie sur place — c'est le seul fichier partagé, et les conflits y sont rares et lisibles.

## Récupérer une correction du socle

À la première installation du site :

```bash
git remote add upstream https://github.com/jean-ely-pro/site-vitrine-cockpit-php.git
```

Puis à chaque correction à récupérer :

```bash
git fetch upstream
git merge upstream/main
composer test
php bin/purge-cache.php
```

Si un conflit apparaît sur un fichier de `templates/` ou sur `site.css`, c'est que la règle
ci-dessus n'a pas été suivie : déplacer la personnalisation du côté `templates-client/`.

## Mise en route

```bash
composer install
php bin/install-cockpit.php
cp .env.example .env
php bin/cockpit-init.php          # note les mots de passe affichés
composer serve                    # site      → http://localhost:8080
composer serve-admin              # admin     → http://localhost:8090
```

Vérifier que les ports sont libres avant de lancer :

```powershell
Get-NetTCPConnection -LocalPort 8080,8090 -State Listen -ErrorAction SilentlyContinue
```

Aucune sortie = libres. Sinon `php -S` s'arrête sans message et affiche le site d'un autre
processus.

## Où se trouve quoi

Livré avec le socle — **ne pas modifier** :

| Chemin | Contenu |
|---|---|
| `templates/base.html.twig` | en-tête, menu, pied de page, structure commune |
| `templates/page.html.twig` | assemblage des sections d'une page |
| `templates/blocs/*.html.twig` | un fichier = un type de section |
| `templates/partials/image.html.twig` | rendu d'une image — passage obligé |
| `templates/partials/pied.html.twig` | pied de page |
| `public/assets/css/site.css` | base : structure, typographie, accessibilité |

Propre à ce site — **c'est là que vous écrivez** :

| Chemin | Contenu |
|---|---|
| `templates-client/blocs/*.html.twig` | vos types de section |
| `templates-client/…` | vos remplacements de gabarits livrés |
| `public/assets/css/client.css` | vos styles |
| `cockpit/models/pages.model.php` | champs des sections — seul fichier partagé |

## Démarche

1. Découper la maquette en sections réutilisables.
2. Pour chaque section : un fichier dans `templates-client/blocs/`, ses champs dans
   `cockpit/models/pages.model.php`, ses styles dans `client.css`.
3. `php bin/install-cockpit.php --force`
4. Composer les pages dans l'administration.
5. `composer test` et `php bin/verifier-accessibilite.php`

## Ajouter un type de section

Modèle à recopier : `templates/blocs/temoignages.html.twig`, commenté ligne à ligne.

### 1. Le partial

`templates-client/blocs/mon-type.html.twig`. Le nom du fichier devient le nom du type.

Variables reçues, et uniquement celles-ci :

| Variable | Contenu |
|---|---|
| `bloc` | valeurs saisies |
| `site` | identité : `nom`, `email`, `telephone`, `adresse`, `horaires`, `reseaux` |
| `titre` | titre de la section |
| `niveau` | `1` ou `2` — niveau de la balise de titre |
| `premier` | vrai si première section de la page |

Le bloc `formulaire` reçoit en plus `slug`, `accueilSlug`, `contactActif`, `jetonContact`,
`formulaire`.

### 2. Les champs

Dans `cockpit/models/pages.model.php` :

- ajouter le type dans les `options` du champ `type` ;
- déclarer les champs propres au type, chacun avec
  `'condition' => "data.type === 'mon-type'"`.

Types de champ disponibles : `text`, `richtext`, `boolean`, `select`, `color`, `date`,
`datetime`, `number`, `asset`, `set`, `table`, `tags`, `content-item-link`.

Contenu répétable : un champ `set` avec `'multiple' => true` et `opts.fields`.

### 3. Appliquer

```bash
php bin/install-cockpit.php --force
```

Réécrit les modèles depuis `cockpit/models/`. Toute modification faite directement dans
l'administration est perdue — le dépôt fait foi.

## Règles

Vérifiées automatiquement. Les ignorer fait échouer `composer test` ou
`bin/verifier-accessibilite.php`.

| Règle | Pourquoi |
|---|---|
| `<h{{ niveau }}>` et jamais `<h2>` en dur | un seul `<h1>` par page |
| Images via `partials/image.html.twig` | copies allégées, `srcset`, dimensions |
| Un champ `asset` impose un champ `alt` au même niveau | enregistrement refusé sinon |
| Aucune ressource externe | police, script ou image distants bloqués par la politique de contenu |
| Titres limités à `h2` et `h3` dans le texte enrichi | corrigés à l'enregistrement |
| `php bin/purge-cache.php` après modification de gabarit ou de CSS | sinon l'ancienne version continue d'être servie |

## Images

```twig
{% include 'partials/image.html.twig' with {
    asset: bloc.image,
    alt: bloc.alt,
    classe: 'mon-bloc__image',
    sizes: '(min-width: 48rem) 30rem, 100vw',
    eager: premier
} only %}
```

| Paramètre | |
|---|---|
| `asset` | le champ image du bloc |
| `alt` | description ; `''` si l'image est décorative |
| `classe` | classe CSS |
| `sizes` | largeur d'affichage réelle, pour que le navigateur choisisse la bonne copie |
| `eager` | `true` uniquement au-dessus de la ligne de flottaison |

Copies produites : 480, 960 et 1440 px en WebP, jamais plus larges que l'original.

## CSS

Tout dans `public/assets/css/client.css`. Nommer les classes d'après le partial :
`bloc-mon-type`, `bloc-mon-type__element`.

Variables disponibles :

```css
--couleur-texte        --couleur-texte-doux    --couleur-lien
--couleur-fond         --couleur-fond-doux     --couleur-trait
--largeur
```

`--couleur-lien` et `--couleur-texte` sont écrasées par les couleurs saisies dans
l'administration, via `couleurs.css` chargé après. Ne pas les coder en dur.

Interdits : `opacity` sur du texte, polices distantes, contraste inférieur à 4,5:1.

## Vérifier

```bash
composer test                          # 156 tests, moins d'une seconde
php bin/verifier-accessibilite.php     # sur le HTML réellement servi
php bin/purge-cache.php                # après toute modification de gabarit ou de CSS
```

`verifier-accessibilite.php` contrôle : langue, titre, méta-description, `<main>`, titre
principal unique, hiérarchie des titres, lien d'évitement, descriptions et dimensions
d'images, intitulés de formulaire, ressources tierces, transparence sur du texte, contraste
des couleurs.

## Adresses réservées

`contact`, `actualites`, `mentions-legales`, `confidentialite`. Une page portant l'un de ces
slugs est refusée à l'enregistrement.

Pages écrites par le site, non modifiables depuis l'administration :
`/mentions-legales` et `/confidentialite`, composées depuis l'identité et le singleton
*Mentions légales*.

## Pièges

| Symptôme | Cause |
|---|---|
| Modification invisible | cache non purgé |
| Conflit git sur `site.css` ou `templates/` | styles ou gabarits écrits du mauvais côté |
| Section absente de la page | pas de partial du même nom dans `templates-client/blocs/` ni `templates/blocs/` |
| Champs absents dans l'administration | `install-cockpit.php --force` non lancé |
| Enregistrement refusé | image sans description, ou adresse réservée |
| Image sans `srcset` | balise `<img>` écrite à la main |
| `composer test` échoue sur les titres | `<h2>` en dur au lieu de `<h{{ niveau }}>` |
| Le site affiche celui d'un autre dossier | port déjà occupé, serveur non démarré |

## Plusieurs sites

Un site = un dépôt, avec ce socle en `upstream`. Voir « Récupérer une correction du socle »
plus haut.

Un type de section qui revient sur plusieurs sites a sa place dans le socle : déplacer le
fichier de `templates-client/blocs/` vers `templates/blocs/`, avec ses champs et ses styles.
Les sites suivants en héritent.

## Hors du champ du développeur

Structure des collections, comptes, rôles, clés d'API : voir [securite.md](securite.md).
Mise en ligne et mises à jour : voir [installation-mutualise.md](installation-mutualise.md).
