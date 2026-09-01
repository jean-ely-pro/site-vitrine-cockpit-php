# Créer un site et le tenir à jour depuis le socle

Un site client est créé depuis le dépôt socle, puis vit dans son propre dépôt. Les corrections
apportées au socle se récupèrent ensuite par fusion, site par site.

## Versions du socle

Chaque état publié du socle porte une étiquette Git — `v1.0.0`, `v1.1.0`, `v2.0.0` — et le
fichier `VERSION`, à la racine, indique celle qui est installée. Ce que chaque version apporte
est dans [CHANGELOG.md](../CHANGELOG.md).

Le rang qui change annonce ce que la fusion va demander :

| Rang | Exemple | Ce que le site doit faire |
|---|---|---|
| **MAJEUR** | `1.4.0` → `2.0.0` | une intervention manuelle — déplacer des fichiers, modifier `.env`, migrer des données. La marche à suivre est donnée sous la version dans le journal. |
| **MINEUR** | `1.3.0` → `1.4.0` | rien de plus que la fusion |
| **CORRECTIF** | `1.3.0` → `1.3.1` | rien de plus que la fusion |

Lire le journal **avant** de fusionner un changement de rang majeur : l'intervention se prépare
hors ligne, elle ne s'improvise pas sur un site en service.

## 1. Créer le dépôt du site

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

## 2. Déclarer le socle — une fois par site

```bash
git remote add socle https://github.com/jean-ely-pro/site-vitrine-cockpit-php.git
git fetch socle
git remote -v
```

`origin` est le dépôt du site. `socle` ne sert qu'à lire : ne jamais y pousser.

## 3. Première fusion

Le dépôt créé depuis un template démarre sur un historique neuf, sans ancêtre commun avec le
socle. La première fusion demande une option supplémentaire :

```bash
git checkout -b maj-socle
git merge socle/main --allow-unrelated-histories
```

Les fichiers identiques des deux côtés fusionnent sans intervention, et ceux que le socle a
ajoutés depuis sont repris. Seuls les fichiers dont le contenu diffère sont signalés :

```bash
git diff --name-only --diff-filter=U
```

**Si le site n'a pas encore été personnalisé**, prendre le socle en bloc :

```bash
git checkout --theirs -- .
git add -A
git commit -m "Fusionner le socle"
```

**Si le site a déjà été personnalisé**, ouvrir chaque fichier listé, garder les deux apports,
puis `git add` fichier par fichier avant de committer.

## 4. Fusions suivantes

Un ancêtre commun existe désormais : plus aucune option.

```bash
git fetch socle --tags
git checkout -b maj-socle
git merge v1.4.0
```

Fusionner l'étiquette plutôt que `socle/main` : ce qui entre dans le site est alors un état
publié et décrit dans le journal, et non l'avancement du moment. `VERSION` fait partie de la
fusion, donc il porte le nouveau numéro sans qu'on ait à l'écrire.

`git tag -l` après le `fetch` liste les versions disponibles.

## 5. Après chaque fusion

| Commande | Quand |
|---|---|
| `composer install` | `composer.lock` a changé |
| `php bin/install-cockpit.php --force` | la version de Cockpit a changé dans `bin/install-cockpit.php` |
| `php bin/purge-cache.php` | toujours |
| `composer test` | toujours |

Puis ouvrir une page du site et `/admin`, et pousser la branche pour relecture :

```bash
git push -u origin maj-socle
```

## Ce qui entre en conflit

| Fichiers | À la fusion |
|---|---|
| `templates-client/` | jamais — le socle n'y écrit pas |
| `.env`, `var/`, `public/media/`, `public/admin/` | jamais — non versionnés |
| `templates/`, `src/`, `public/assets/` | conflit s'ils ont été modifiés dans le site |

Placer toute personnalisation dans `templates-client/` — voir
[guide-integration.md](guide-integration.md).

## Reprendre un seul correctif

```bash
git fetch socle
git log --oneline socle/main
git cherry-pick <empreinte>
```
