# Médias

## Ce qui se passe quand une image est envoyée

Le client envoie son image depuis le gestionnaire de médias de Cockpit. Aussitôt, des **copies
allégées** sont fabriquées, une par largeur utile :

| Copie | Largeur | Format |
|---|---|---|
| `w480` | 480 px | WebP |
| `w960` | 960 px | WebP |
| `w1440` | 1440 px | WebP |

Une copie n'est jamais plus large que l'original : agrandir ajouterait du poids sans ajouter
de détail. Une image de 1200 px n'aura donc que deux copies.

Les copies sont faites **à l'envoi**, pas au rendu d'une page : une page est rendue à nouveau
chaque fois que le cache est vidé, une image n'est envoyée qu'une fois.

Leurs adresses sont inscrites sur le média lui-même, si bien que le site n'a aucun appel à
faire pour les connaître.

## Ce que reçoit le visiteur

Le navigateur choisit la copie qui convient à la place dont il dispose et à la finesse de son
écran, par `srcset` et `sizes`. Un navigateur qui ne saurait pas choisir reçoit la copie de
960 px — jamais l'original en pleine taille.

Chaque image porte ses dimensions, pour que la page ne se décale pas pendant le chargement, et
n'est chargée qu'au moment utile — sauf celle du bandeau, qui est prioritaire.

## Recadrage

Cockpit gère un **point focal** : dans la fiche d'un média, le bouton en forme de cible permet
de désigner le point à ne jamais perdre. Les copies recadrées le respectent.

Changer le point focal produit de nouvelles copies à l'enregistrement — le nom d'un fichier
généré découle de ses réglages, il n'y a donc rien à purger à la main.

## La description de l'image est obligatoire

Partout où le modèle de contenu place un champ **Description de l'image** à côté d'une image,
remplir l'image oblige à remplir la description. L'enregistrement est refusé sinon, avec un
message qui explique pourquoi.

Une image sans description est illisible pour qui utilise un lecteur d'écran, et n'affiche
rien du tout lorsqu'elle ne charge pas.

Seule exception : dans la liste des actualités, la vignette est purement décorative — le titre
juste à côté dit déjà la même chose — elle est donc rendue avec une description vide, ce qui
est la bonne façon de signaler une image décorative.

## Alerte de poids

Cockpit affiche déjà le format, le poids et les dimensions. Ce qu'il ne dit pas, c'est à partir
de quand un chiffre devient un problème. Un avertissement apparaît donc au-delà de :

- **600 ko** de poids, ou
- **2600 px** de large.

Rien n'est refusé : le site sert de toute façon des copies allégées. L'avertissement évite
seulement de conserver un original inutilement lourd, qui ralentit l'administration et les
sauvegardes.

## Où vivent les fichiers

```
public/medias/AAAA/MM/JJ/…    images envoyées
public/medias/variantes/…     copies allégées
```

Les deux sont servis directement par le serveur web, à l'adresse `/medias`.

Ils vivent sous la racine du site, **et non dans le dossier de l'administration**. Une page
publique ne nomme donc jamais `/admin`, et rien sous `/admin` n'a besoin d'être lisible depuis
l'extérieur : le dossier `storage` de Cockpit — modèle de contenu, fichiers temporaires,
caches — est fermé en entier.

`public/medias/.htaccess` est versionné et part avec le site. Il ouvre le dossier à la
consultation et refuse ce qui pourrait s'y exécuter. Cockpit refuse déjà ces fichiers à
l'envoi, par extension et par type MIME ; la règle est la seconde barrière.

## Temps de traitement

Les copies sont fabriquées pendant l'envoi. Mesuré sur ce poste : **2,3 s** pour une image de
4000 × 3000 px et ses trois copies. Sur un hébergement mutualisé lent, compter davantage.

Si un envoi devait échouer par dépassement du temps d'exécution, l'image reste en place —
seules les copies manquent, et le site sert l'original. Il suffit alors de lancer :

```bash
php bin/generer-variantes.php
```

C'est aussi la raison de conseiller au client des images d'environ 2000 px de large plutôt que
les fichiers bruts d'un appareil photo.

## Régénérer les copies

Après un changement de largeurs, ou pour des images antérieures à ce mécanisme :

```bash
php bin/generer-variantes.php            # ne refait que ce qui manque
php bin/generer-variantes.php --refaire  # refait tout
```

Le script enregistre aussi à nouveau les contenus : chaque page garde une copie du média
qu'elle utilise, sans quoi elle continuerait à servir l'original. Le cache des pages est vidé
au passage.

## Changer les largeurs

Elles sont déclarées dans `cockpit/config.php`, sous `assets.presets`. Après modification :

```bash
php bin/install-cockpit.php --force
php bin/generer-variantes.php
```

Les valeurs `sizes` des gabarits décrivent la place occupée par l'image ; les ajuster si la
mise en page change.
