# Créer un site et le tenir à jour depuis le socle

Un site client est créé depuis le dépôt socle, puis vit dans son propre dépôt. Les corrections
apportées au socle se récupèrent ensuite par fusion, site par site.

Deux situations, dans l'ordre où elles se présentent le plus souvent : mettre à jour un site
qui existe, et en installer un nouveau.

## Mettre à jour un site

Tout se passe dans le dépôt du site, sur un poste de développement. La mise en ligne vient
après, à l'étape 6.

### 1. Voir où en est le site

```bash
cd <site-client>
cat VERSION
```

Le numéro affiché est celui du socle installé. S'il n'y a pas de fichier `VERSION`, le site est
antérieur à `v1.0.0` : la première fusion le posera.

### 2. Choisir la version à prendre

```bash
git fetch socle --tags
git tag -l 'v*'
```

`git tag -l` sans motif listerait aussi les étiquettes propres au site ; `'v*'` ne garde que
celles du socle.

Lire ensuite ce qu'apporte chaque version entre celle du site et celle visée, dans
[CHANGELOG.md](../CHANGELOG.md).

> **Le premier chiffre est le seul qui coûte.** `1.4.0` → `2.0.0` annonce une intervention
> manuelle — déplacer des fichiers, modifier `.env`, migrer des données. La marche à suivre est
> donnée sous la version dans le journal, et se prépare **avant** de fusionner : elle ne
> s'improvise pas sur un site en service. Tout autre changement — `1.3.0` → `1.4.0`,
> `1.3.0` → `1.3.1` — se fusionne sans rien de plus.

Rien n'oblige à passer les versions une par une : fusionner directement la plus récente reprend
tout ce qui la précède. Si plusieurs versions majeures sont franchies d'un coup, appliquer
leurs interventions dans l'ordre du journal.

### 3. Fusionner

```bash
git checkout main
git pull
git checkout -b maj-socle
git merge v2.0.4
```

Toujours sur une branche : la fusion se relit avant d'entrer dans `main`.

`VERSION` fait partie de la fusion, donc le site porte le nouveau numéro sans qu'on ait à
l'écrire.

L'étiquette est fusionnée plutôt que `socle/main` : ce qui entre dans le site est un état
publié et décrit dans le journal, et non l'avancement du moment.

### 4. Régler les conflits, s'il y en a

```bash
git diff --name-only --diff-filter=U
```

Vide, il n'y en a pas — passer à l'étape suivante. Sinon, ouvrir chaque fichier listé, garder
les deux apports, puis :

```bash
git add <fichier>
git commit
```

La liste des fichiers susceptibles de rentrer en conflit est plus bas.

### 5. Après la fusion

Les quatre commandes, dans cet ordre, sans condition :

```bash
composer install                       # sans effet si composer.lock n'a pas bougé
php bin/install-cockpit.php --force    # obligatoire, voir ci-dessous
php bin/purge-cache.php
composer test
```

**`--force` n'est pas facultatif.** `public/admin/` n'est pas versionné : une fusion qui touche
`cockpit/config.php`, `cockpit/bootstrap.php`, `cockpit/models/` ou `cockpit/addons/` ne change
rien tant que le script ne les a pas recopiés. Sans `--force`, il constate que la version de
Cockpit est déjà installée et s'arrête sans rien faire.

La commande est sans danger si rien n'a changé : elle réinstalle le cœur de Cockpit, laisse
`var/`, `public/medias/` et `public/admin/.env` intacts, et ne retélécharge l'archive que si
elle est absente.

Ouvrir enfin une page du site et `/admin`, puis pousser pour relecture :

```bash
git push -u origin maj-socle
```

### 6. Mettre à jour le site en ligne

Les étapes précédentes n'ont touché que le dépôt. Une fois la branche fusionnée dans `main`,
reporter le socle sur l'hébergement. Deux cas, selon ce que l'hébergeur permet.

#### Avec un accès SSH et un dépôt git sur le serveur

```bash
cd ~/<dossier-du-site>
git status                             # doit être propre : la prod n'a rien à committer
git pull
php bin/install-cockpit.php --force
php bin/purge-cache.php
```

À poser **une fois par hébergement**, avant la première mise à jour :

```bash
git config pull.ff only
```

Sur une production saine, `git pull` fait exactement ce que ferait `git merge --ff-only
origin/main` : le serveur n'a aucun commit local, la fusion est une simple avance rapide. Les
deux commandes sont indiscernables — jusqu'au jour où le serveur a dérivé, parce qu'un
correctif y a été committé sur place ou qu'une reprise y a laissé un commit.

- `git pull` fusionne quand même, et crée un commit de fusion que le serveur est seul à
  porter. Chaque mise à jour suivante en ajoute un.
- Si la fusion conflicte, git écrit les marqueurs `<<<<<<< HEAD` **dans les fichiers** — au
  milieu d'un `public/index.php` que PHP est en train de servir.
- Avec `pull.ff only`, git s'arrête sur `Not possible to fast-forward, aborting`. Rien n'est
  touché, le site continue de répondre, et la dérive se règle à froid.

Ce n'est donc pas une autre opération, c'est la même avec un garde-fou. Le réglage fixe au
passage le comportement de `git pull`, qui dépend sinon de la version de git et de la
configuration de la machine.

#### Par envoi de fichiers

Voir [installation-mutualise.md](installation-mutualise.md). Après l'envoi, relancer les deux
commandes ci-dessus si la ligne de commande existe.

Sans ligne de commande, enregistrer n'importe quel contenu depuis l'administration vide le
cache ; les fichiers de `public/admin/` doivent alors être préparés en local puis envoyés,
puisque `install-cockpit.php` ne peut pas tourner sur place.

## Ce qui entre en conflit

| Fichiers | À la fusion |
|---|---|
| `templates-client/` | jamais — le socle n'y écrit pas |
| `.env`, `var/`, `public/medias/`, `public/admin/` | jamais — non versionnés (sauf `public/medias/.htaccess`) |
| `templates/`, `src/`, `public/assets/` | conflit s'ils ont été modifiés dans le site |

Placer toute personnalisation dans `templates-client/` — voir
[guide-integration.md](guide-integration.md).

## Installer un nouveau site

Trois étapes, une seule fois par client.

### 1. Créer le dépôt du site

Sur GitHub, ouvrir le dépôt socle et cliquer **Use this template → Create a new repository**.

| Champ | Valeur |
|---|---|
| Nom | celui du client, par exemple `site-boulangerie-martin` |
| Visibilité | **Private** |
| Include all branches | décoché |

Puis cloner et installer :

```bash
git clone https://github.com/<compte>/<site-client>.git
cd <site-client>
composer install
php bin/install-cockpit.php
cp .env.example .env
php bin/cockpit-init.php
```

La suite de l'installation locale est dans
[developpement-local.md](developpement-local.md).

### 2. Déclarer le socle

```bash
git remote add socle https://github.com/jean-ely-pro/site-vitrine-cockpit-php.git
git fetch socle --tags
git remote -v
```

`origin` est le dépôt du site. `socle` ne sert qu'à lire : ne jamais y pousser.

### 3. Première fusion

Un dépôt créé depuis un template démarre sur un historique neuf, sans ancêtre commun avec le
socle. La première fusion demande donc une option que les suivantes n'auront pas :

```bash
git checkout -b maj-socle
git merge v2.0.4 --allow-unrelated-histories
```

Les fichiers identiques des deux côtés fusionnent sans intervention, et ceux que le socle a
ajoutés depuis sont repris. Seuls ceux dont le contenu diffère sont signalés.

**Si le site n'a pas encore été personnalisé**, prendre le socle en bloc :

```bash
git checkout --theirs -- .
git add -A
git commit -m "Fusionner le socle"
```

**Si le site a déjà été personnalisé**, ouvrir chaque fichier listé par
`git diff --name-only --diff-filter=U` et garder les deux apports.

Reprendre ensuite à l'étape 5 de la mise à jour.

## Cas particuliers

### Reprendre un seul correctif

Quand une seule correction est attendue, sans prendre le reste :

```bash
git fetch socle
git log --oneline socle/main
git cherry-pick <empreinte>
```

Le site garde alors son numéro de version : `VERSION` n'est pas repris. Le noter dans le suivi
du client, sans quoi l'écart devient invisible.

### Revenir à la version précédente

Chaque version majeure décrit son retour arrière sous son entrée dans
[CHANGELOG.md](../CHANGELOG.md) : commit à annuler, fichiers à replacer, adresses déjà
publiées.

### Comment les étiquettes sont posées

Une étiquette ne se pose pas à chaque fusion sur le socle : plusieurs correctifs peuvent
attendre la même. On en ajoute une quand il y a quelque chose qu'un site doit pouvoir
reprendre — c'est l'étiquette, et non la branche, qui est l'unité livrée.
