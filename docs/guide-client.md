# Ce que le client peut faire, et comment

Ce document décrit l'administration telle que la voit le client. Il sert de base au guide qui
lui sera remis, et de référence pour vérifier qu'il reste dans les clous.

## Ce qu'il voit

Le compte `client` porte un rôle qui ne donne accès qu'au contenu :

| Il peut | Il ne peut pas |
|---|---|
| Modifier l'identité du site | Modifier la structure des collections |
| Créer, modifier, publier des pages | Créer ou modifier des comptes |
| Modifier le menu | Gérer les rôles et les clés d'API |
| Publier des actualités | Consulter les journaux |
| Envoyer des images | Ajouter des champs ou des types de section |

**Le client ne peut pas casser son site** : la structure est figée dans le dépôt, pas dans
l'administration. Au pire, il publie un contenu inexact — et le corrige.

## Créer une page à partir d'un modèle

Trois modèles sont fournis, laissés **non publiés** : « Modèle — Services »,
« Modèle — À propos », « Modèle — Tarifs ».

1. *Contenu → Pages*, ouvrir le modèle voulu
2. *Dupliquer* (menu de l'élément)
3. Remplacer le titre et l'adresse de la page
4. Remplir les sections, supprimer celles qui ne servent pas
5. Renseigner l'onglet *Référencement*
6. **Publier**

Un modèle n'est jamais en ligne : il reste non publié, et sert de point de départ autant de
fois que nécessaire.

## Ajouter une page au menu

*Contenu → Menu*, ajouter une entrée, choisir la page, saisir le libellé. Le menu ne peut
pointer que sur des pages existantes — un lien mort est impossible. Une page dépubliée
disparaît d'elle-même du menu.

## Publier une actualité

*Contenu → Actualités → Ajouter*. Titre, adresse, date, catégorie et résumé ; l'image et son
texte de remplacement ; puis le texte. Enfin **Publier**.

Les actualités apparaissent à `/actualites`, la plus récente en premier, et l'entrée
« Actualités » s'ajoute au menu dès qu'il y en a une. Une actualité non publiée n'est visible
nulle part.

## Le référencement, page par page

L'onglet *Référencement* porte deux champs, chacun avec son compteur de caractères :

- **Titre dans les résultats de recherche** — environ 60 caractères. Vide, le titre de la page
  est repris.
- **Résumé** — environ 155 caractères. C'est le texte affiché sous le titre dans Google.

Pour une actualité, c'est le champ *Résumé* qui joue ce rôle.

## Les horaires

*Identité du site → Horaires*. Le champ est libre : ce qui est saisi est affiché tel quel sur
le site — « 9h – 12h, 14h – 18h30 », « Fermé le lundi », « sur rendez-vous ».

Les moteurs de recherche reçoivent en plus une version lisible par machine, mais **seulement
quand les horaires se lisent sans ambiguïté**. « sur rendez-vous » ou « 24h/24 » s'affichent
normalement sur le site et sont laissés de côté pour les moteurs : mieux vaut ne rien leur
annoncer qu'annoncer un horaire faux.

## L'aperçu quand un lien est partagé

*Identité du site → Image de partage*. C'est l'image qui s'affiche quand une page du site est
partagée sur les réseaux sociaux ou envoyée dans une messagerie. Format paysage, au moins
1200 × 630 pixels.

Vide, l'image de la page partagée est reprise ; à défaut, le logo. Le titre et le résumé de
l'aperçu sont ceux de la page : il n'y a rien de plus à saisir.

## L'éditeur de texte

Il ne propose que ce que la structure du site autorise : **Titre 2**, **Titre 3**, gras,
italique, listes et liens. Pas de Titre 1 — chaque page en a déjà un, son titre — ni de
couleurs ou de tableaux, qui relèvent de la mise en forme du site et non du contenu.

Un texte collé depuis un traitement de texte est **corrigé à l'enregistrement** : les niveaux
de titre sont ramenés dans la plage autorisée. Il n'y a donc rien à surveiller.

## Publier, c'est mettre en ligne

Chaque page et chaque actualité porte un état : **Publié**, **Non publié** ou **Archivé**. Seul
« Publié » est visible en ligne.

Enregistrer un contenu **vide le cache du site** : la modification est visible immédiatement,
sans délai ni manipulation.

## Ce qui reste à faire par le prestataire

- Ajouter un type de section, une collection ou un champ
- Créer un compte, changer un rôle, créer une clé d'API
- Mettre Cockpit et PHP à jour

Ces gestes engagent la structure ou la sécurité du site. Les demander au prestataire.
