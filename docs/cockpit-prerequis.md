# Cockpit — version retenue, prérequis et capacités

État vérifié le **3 août 2026**, avant installation. Ce document sert de référence pour
l'installation initiale et pour les mises à jour ultérieures sur l'hébergement du client.

## Version retenue

| | |
|---|---|
| Paquet | `cockpit-hq/cockpit` |
| Version retenue | **2.14.0** |
| Date de publication | 30 mars 2026 |
| Mode d'installation | archive officielle déposée dans `/cockpit` |
| Base de données | SQLite (stockage par défaut de la branche 2.x) |

L'archive officielle embarque ses propres dépendances (`lib/vendor`). Elle est retenue plutôt
que `composer create-project` parce que la mise en ligne sur un hébergement mutualisé se fait
par simple envoi de fichiers, sans Composer côté serveur.

### Activité du projet

Le dernier commit publié en amont date du **30 mars 2026**, le jour de la sortie de 2.14.0.
Aucune version n'a été publiée depuis. Cette inactivité est à surveiller : elle conditionne le
délai de correction des failles signalées (voir ci-dessous) et donc l'effort de maintenance sur
chaque hébergement client.

## Prérequis serveur

### PHP

Version minimale **8.3**. Extensions requises :

| Extension | Rôle |
|---|---|
| `pdo`, `pdo_sqlite` | base de données SQLite |
| `gd` | traitement des images (miniatures, conversions) |
| `curl` | appels sortants |
| `fileinfo` | détection du type des fichiers envoyés |
| `zip` | archives (sauvegardes, envoi de médias groupés) |
| `json` | intégrée à PHP 8 |

### Apache

- `mod_rewrite` actif — nécessaire à l'administration comme au site public.
- Écriture autorisée sur `cockpit/storage/` et sur le répertoire de cache des pages.

### Vérification en ligne de commande

```bash
php -r 'foreach (["pdo_sqlite","gd","curl","fileinfo","zip"] as $e) printf("%-10s %s\n", $e, extension_loaded($e) ? "ok" : "MANQUANT");'
```

Sur un poste Windows/Laragon, `zip` est livré mais désactivé par défaut : décommenter
`extension=zip` dans le `php.ini` utilisé par la ligne de commande.

## Capacités vérifiées

### Rôles et permissions — **disponible, suffisant**

Les rôles se définissent dans l'administration (identifiant, nom, description). Les permissions
se donnent à deux niveaux :

- par module — contenu, médias, API et sécurité, langues, pages, utilisateurs ;
- **par collection ou singleton pris individuellement** — un rôle peut modifier une collection
  et n'avoir qu'un accès en lecture sur une autre.

Le rôle `admin` accorde tout et n'est pas modifiable.

Conséquence pour le produit : le client reçoit un rôle sans le droit de créer ou modifier la
structure des collections. Il remplit le contenu, il ne peut pas casser le modèle.

### Double authentification — **disponible dans le socle**

La double authentification par code à usage unique est intégrée au socle de Cockpit
(`modules/App/Helper/TWFA.php`, appuyée sur la bibliothèque `robthree/twofactorauth`). Elle est
exposée dans la fiche utilisateur et dans le contrôleur d'authentification.

Aucune couche d'authentification serveur supplémentaire n'est donc nécessaire pour atteindre
l'objectif de protection de l'administration. Le durcissement serveur (HTTPS forcé, en-têtes de
sécurité, exclusion du cache) reste requis par ailleurs.

### API de contenu — **REST, avec clés porteuses d'un rôle**

- Préfixe : `https://domaine.tld/api/`
- Authentification : en-tête `api-key: <jeton>`
- Lecture d'une collection : `GET /api/content/items/{modele}`
- Lecture d'un singleton : `GET /api/content/item/{modele}`
- Écriture : `POST /api/content/item/{modele}` — suppression : `DELETE /api/content/item/{modele}/{id}`
- Paramètres utiles : `filter` (syntaxe de requête Mongo), `sort`, `limit`, `skip`, `fields`,
  `populate`, `locale`

Deux natures de clés : les clés liées à un compte utilisateur et les clés autonomes. **Chaque
clé porte un rôle**, et ce rôle définit ce que la clé autorise. La séparation exigée entre une
clé de lecture pour le site public et une clé d'écriture est donc réalisable telle quelle.

Une API GraphQL existe également dans le socle ; elle n'est pas retenue, le site public
n'ayant besoin que de lectures simples.

## Sécurité — avis publiés

26 avis de sécurité ont été publiés pour ce paquet depuis 2023, majoritairement des envois de
fichiers non restreints et des injections de script. Presque tous sont corrigés dans les
versions récentes. **Un avis reste ouvert sur la version retenue :**

| Avis | Portée | Gravité | Versions touchées |
|---|---|---|---|
| [GHSA-ch4j-vcf5-58x5](https://github.com/advisories/GHSA-ch4j-vcf5-58x5) (CVE-2026-23695) | Injection de script persistante via l'option « Display template » du champ *Set* | moyenne | **≤ 2.14.0**, aucun correctif publié |

Exposition réelle sur ce produit : l'option concernée n'est accessible qu'à un compte autorisé à
**modifier la structure** des collections. Le modèle de contenu étant figé et le rôle du client
ne comportant pas ce droit, le vecteur n'est pas atteignable par un compte client. Il reste
atteignable par un compte d'administration — d'où l'obligation de double authentification sur
ces comptes.

À surveiller : publication d'une version corrective, à appliquer sur chaque hébergement.

## Procédure de mise à jour

Il n'existe pas de vue centralisée : chaque hébergement client se met à jour séparément.

1. Relever la version installée dans l'administration.
2. Récupérer l'archive de la nouvelle version.
3. Sauvegarder `cockpit/storage/` (base SQLite et médias) **avant** toute écriture.
4. Envoyer les fichiers en écrasant tout sauf `cockpit/storage/` et `cockpit/config/`.
5. Ouvrir l'administration, vérifier la connexion et l'affichage du contenu.
6. Purger le cache des pages.
7. Consigner la date et la version dans le suivi du client.

## Sources

- <https://packagist.org/packages/cockpit-hq/cockpit>
- <https://github.com/Cockpit-HQ/Cockpit>
- <https://getcockpit.com/documentation/core/api/content>
- <https://getcockpit.com/documentation/core/api/authentication>
- <https://getcockpit.com/documentation/core/concepts/roles-permissions>
