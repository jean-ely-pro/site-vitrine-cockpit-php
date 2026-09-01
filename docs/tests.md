# Tests et vérification d'une mise en ligne

Deux contrôles différents, qui ne se remplacent pas : la suite de tests lit le code, le script
de vérification lit le site réellement servi.

## La suite de tests

```bash
composer test
```

234 tests couvrent les garde-fous du produit : ce qui décide de ce qu'un visiteur reçoit, et ce
qui empêche le site d'être cassé depuis l'administration.

| Ce qui est protégé | Exemples |
|---|---|
| Cache de pages | jamais une page d'erreur, jamais une adresse avec paramètres, aucune remontée de dossier |
| Formulaire de contact | consentement jamais supposé, retour toujours sur le site, anti-spam, limite par adresse |
| Couleurs | une couleur sous 4,5:1 n'atteint jamais le site |
| Référencement | horaires ambigus laissés de côté, aucun champ vide publié |
| Aperçu partagé | adresse revendiquée absolue ou absente, jamais fausse ; image en adresse complète |
| Site en ligne | l'adresse revendiquée est celle où la page répond — un `SITE_URL` erroné est signalé |
| Accessibilité | chaque défaut détectable est vérifié sur une page fautive |
| Mots de passe | longueur, variété, mots courants, nom du compte |
| Niveaux de titre | corrigés à l'enregistrement, sections imbriquées comprises |
| Descriptions d'images | exigées dès qu'une image est posée, jamais sans image |
| Brouillons | jamais demandés au service de contenu : seul l'état publié l'est |
| Amorçage de l'administration | les classes et chemins cités par les addons existent bien |
| Modèles de l'administration | types de champ réellement enregistrés, libellés de listes interpolés |
| Types de section | chaque type proposé au client a son gabarit, et réciproquement |

Les tests ne touchent pas au réseau et ne démarrent pas Cockpit : ils s'exécutent en moins
d'une seconde. Un seul lit les fichiers de l'administration installée, pour confronter les
types de champ aux composants que Cockpit enregistre ; il est ignoré tant que
`bin/install-cockpit.php` n'a pas tourné.

## La vérification d'une mise en ligne

```bash
php bin/verifier-accessibilite.php https://domaine-du-client.tld
```

Le script lit **le HTML réellement servi** — cache compris — sur toutes les adresses du plan du
site : langue, titre unique, hiérarchie des titres, descriptions d'images, dimensions,
intitulés de formulaire, ressources tierces, transparence sur du texte et contraste des
couleurs. Aucune dépendance à installer.

Il confronte aussi **l'adresse que chaque page revendique** à celle où elle vient d'être lue.
C'est le seul contrôle qui attrape un `SITE_URL` erroné : la page se rend correctement, mais
annonce aux moteurs et aux réseaux une adresse qui n'est pas la sienne.

Il se lance **depuis un poste de développement**, jamais depuis l'hébergement — `src/Audit/` et
`bin/verifier-accessibilite.php` ne sont pas envoyés en production. Voir
[installation-mutualise.md](installation-mutualise.md).

Les contrôles de sécurité à repasser sur chaque installation sont dans
[securite.md](securite.md).
