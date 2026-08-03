# Site vitrine — Cockpit + PHP

Site vitrine autonome hébergé **entièrement chez le client** : une administration de contenu
et un site public rendu côté serveur, sur un hébergement mutualisé classique.

- **Administration** — Cockpit CMS (PHP + SQLite), à l'adresse `/admin`.
- **Site public** — PHP 8.3 + Twig, rendu côté serveur : le contenu est présent dans le HTML
  de la première réponse.
- **Cache de pages** — le HTML rendu est écrit en fichiers statiques servis par Apache.

Aucun runtime Node n'est requis en production. Le déploiement se fait par envoi de fichiers.

## Documentation

- [Prérequis et capacités de Cockpit](docs/cockpit-prerequis.md)

## Licence

Propriétaire — tous droits réservés.
