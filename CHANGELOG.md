# Journal des versions

Les numéros suivent le [versionnage sémantique](https://semver.org/lang/fr/), avec le sens
qu'il prend pour un socle recopié chez chaque client :

| Rang | Ce qui change | Ce que la mise à jour d'un site demande |
|---|---|---|
| **MAJEUR** | l'installation elle-même : emplacement de fichiers, configuration, données | une intervention manuelle — la marche à suivre et le retour arrière sont décrits sous la version |
| **MINEUR** | une capacité nouvelle | rien de plus que la fusion |
| **CORRECTIF** | une correction | rien de plus que la fusion |

La version installée est inscrite dans le fichier `VERSION`, à la racine. La procédure de
fusion est dans [docs/mise-a-jour-socle.md](docs/mise-a-jour-socle.md).

## 2.0.2 — 2026-09-01

Documentation seule. Rien à faire sur un site existant au-delà de la fusion.

- README ramené à l'installation et à l'orientation, de 353 à 174 lignes. Les sujets traités
  dans `docs/` y sont résumés en une ligne, suivie du renvoi.
- `docs/tests.md` — ce que couvre la suite de tests et ce que lit le contrôle avant mise en
  ligne, jusque-là dans le README.

## 2.0.1 — 2026-09-01

Documentation seule. Rien à faire sur un site existant au-delà de la fusion.

- Procédure de retour à la version précédente, sous 2.0.0 : annulation de la fusion,
  identification du commit à annuler, sort des adresses d'images déjà publiées.
- Le tableau des rangs, en tête de ce journal, annonce désormais que toute version majeure
  décrit aussi son retour arrière.

## 2.0.0 — 2026-09-01

Les médias quittent le dossier de l'administration.

### Pourquoi

Les pages publiques servaient leurs images depuis `/admin/storage/uploads/`. Chaque page
publiait ainsi l'emplacement du panneau d'administration, et l'arborescence des fichiers
désignait le CMS employé. Le reste de `/admin` n'était par ailleurs tenu fermé que par des
fichiers `.htaccess` cachés, qu'un envoi par FTP peut omettre sans que rien ne le signale.

### Ce qui change

- Les médias sont servis depuis `public/medias/`, à l'adresse `/medias`. Aucune page publique
  ne nomme plus `/admin`.
- `public/medias/.htaccess`, versionné et livré avec le site, ouvre le dossier à la
  consultation et refuse ce qui pourrait s'y exécuter.
- `public/.htaccess` refuse `/admin/storage/` — la règle est posée dans le fichier sans lequel
  le site ne fonctionne pas, et non plus seulement dans les fichiers cachés de `public/admin/`.
- `MEDIA_BASE_URL` vaut `/medias` en production, `http://localhost:8080/medias` en
  développement. L'administration s'en sert aussi pour ses aperçus.
- `bin/install-cockpit.php` crée `public/medias/variantes/` et ne crée plus
  `public/admin/storage/uploads/`.

### Mettre à jour un site existant

Après la fusion, sur l'hébergement :

1. Déplacer les fichiers.

   ```bash
   mv public/admin/storage/uploads/* public/medias/
   rm -rf public/admin/storage/uploads
   ```

   `*` ne reprend pas les fichiers cachés, et c'est voulu : l'ancien dossier avait sa propre
   règle d'accès, qui écraserait celle livrée dans `public/medias/`. Vérifier après coup que
   `public/medias/.htaccess` contient bien la ligne `Options -Indexes`.

2. Dans `.env`, remplacer la valeur par `MEDIA_BASE_URL=/medias`.

3. Vérifier que `public/medias/` est inscriptible par le serveur — `755`, parfois `775`.

4. Purger le cache : `php bin/purge-cache.php`, ou enregistrer n'importe quel contenu depuis
   l'administration.

5. Contrôler.

   ```bash
   curl -s https://domaine-du-client.tld/ | grep -c 'admin/storage'    # 0
   curl -sI https://domaine-du-client.tld/admin/storage/ | head -1     # 403
   ```

Les enregistrements d'images portent un chemin relatif, jamais une adresse : **aucune
modification de la base de données n'est nécessaire.**

### Revenir à la version précédente

Le retour déplace des fichiers et ne touche pas davantage à la base de données. À exécuter dans
cet ordre, sur l'hébergement.

1. Annuler la fusion dans le dépôt du site.

   ```bash
   git revert -m 1 <fusion>
   ```

   > [!NOTE]
   > **`<fusion>`** est l'empreinte du commit de **fusion** qui a introduit la version 2.0.0
   > dans le dépôt du site — jamais celle d'un commit du socle.
   >
   > Le retrouver :
   >
   > ```bash
   > git log --oneline --first-parent -5
   > ```
   >
   > Le confirmer avant d'agir. Au commit cherché, `VERSION` vaut `2.0.0` ; chez son premier
   > parent, `1.0.0` :
   >
   > ```bash
   > git show <fusion>:VERSION      # 2.0.0
   > git show <fusion>^1:VERSION    # 1.0.0
   > ```
   >
   > Ce contrôle ne dépend pas du message de commit, qui diffère selon que la fusion vient
   > d'une demande de tirage ou d'un `git merge v2.0.0`.
   >
   > `-m 1` désigne ce premier parent : la ligne du site, celle qu'il faut conserver.
   >
   > `git log --merges -- VERSION` ne renvoie rien — Git écarte les fusions quand l'historique
   > est restreint à un chemin. Passer par `--first-parent`.

2. Recréer le dossier d'origine et sa règle d'accès, écrite par le script d'installation et
   absente du dépôt.

   ```bash
   php bin/install-cockpit.php --force
   ```

3. Replacer les fichiers.

   ```bash
   mv public/medias/2026 public/medias/variantes public/admin/storage/uploads/
   ```

   Nommer les dossiers un à un plutôt qu'écrire `public/medias/*` : `public/medias/.htaccess`
   doit rester où il est. `ls public/medias` donne la liste quand l'administration a servi plus
   d'une année.

4. Dans `.env`, remettre `MEDIA_BASE_URL=/admin/storage/uploads`.

5. Purger le cache.

   ```bash
   php bin/purge-cache.php
   ```

6. Contrôler.

   ```bash
   curl -s https://domaine-du-client.tld/ | grep -c '/medias/'        # 0
   curl -s https://domaine-du-client.tld/ | grep -c 'admin/storage'   # au moins 1
   ```

> [!IMPORTANT]
> **Adresses déjà publiées.** Les images servies sous `/medias` pendant la période 2.0.0
> répondent 404 après le retour. Sur un site en ligne depuis plusieurs semaines, poser la
> redirection dans `public/.htaccess` **avant** de purger, au-dessus des règles du cache :
>
> ```apache
> RewriteRule ^medias/(.*)$ /admin/storage/uploads/$1 [R=301,L]
> ```

Pour repasser en 2.0.0 plus tard, annuler ce retour — `git revert <retour>` — plutôt que
fusionner de nouveau : Git tient la fusion pour déjà faite et n'apporterait rien.

## 1.0.0 — 2026-09-01

Première version numérotée. Elle correspond au socle tel qu'il est déployé et vérifié sur un
hébergement mutualisé.

### Le site public

- Rendu côté serveur en PHP 8.3 avec Twig : le contenu est présent dans le HTML de la première
  réponse.
- Cache de pages écrit en HTML statique et servi par Apache sans démarrer PHP, purgé à la
  publication.
- Pages, actualités, mentions légales et politique de confidentialité, menu, plan du site et
  `robots.txt` générés.
- Données structurées `LocalBusiness`, métadonnées sociales et adresse canonique par page.
- Formulaire de contact avec consentement explicite et garde anti-spam.

### L'administration

- Cockpit 2.14.0, installé par `bin/install-cockpit.php` depuis l'archive officielle dont
  l'empreinte SHA-256 est vérifiée. La base vit hors de la racine web.
- Collections et champs prédéfinis, éditeur limité aux niveaux de titre du contenu.
- Contraste des couleurs calculé et signalé à l'édition.
- Copies allégées des images générées à l'envoi, avec point focal et description obligatoire.

### Les outils

- `bin/verifier-accessibilite.php` : contrôle du HTML réellement servi — contrastes, structure
  des titres, adresses canoniques, feuilles de style.
- `bin/purge-cache.php`, `bin/generer-variantes.php`, `bin/message-test.php`,
  `bin/cockpit-cle.php`.
- Suite de tests : 234 tests, 486 assertions.

### La documentation

Installation sur mutualisé, sécurité de l'installation, développement local, intégration d'une
maquette, guide du client, médias, formulaire de contact, prérequis de Cockpit, création et
mise à jour d'un site depuis le socle.
