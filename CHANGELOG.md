# Journal des versions

Les numéros suivent le [versionnage sémantique](https://semver.org/lang/fr/), avec le sens
qu'il prend pour un socle recopié chez chaque client :

| Rang | Ce qui change | Ce que la mise à jour d'un site demande |
|---|---|---|
| **MAJEUR** | l'installation elle-même : emplacement de fichiers, configuration, données | une intervention manuelle, décrite sous la version |
| **MINEUR** | une capacité nouvelle | rien de plus que la fusion |
| **CORRECTIF** | une correction | rien de plus que la fusion |

La version installée est inscrite dans le fichier `VERSION`, à la racine. La procédure de
fusion est dans [docs/mise-a-jour-socle.md](docs/mise-a-jour-socle.md).

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
