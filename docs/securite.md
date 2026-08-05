# Sécurité de l'installation

Le site et son administration vivent sur l'hébergement du client. Il n'existe **aucune
supervision centralisée** : ce qui suit doit être vérifié sur chaque installation, et la mise
à jour de Cockpit reste le principal travail récurrent (voir
[cockpit-prerequis.md](cockpit-prerequis.md)).

## Ce qui est exposé

| Adresse | Contenu | Accès |
|---|---|---|
| `/` | site public | anonyme, lecture seule |
| `/admin` | administration Cockpit | compte + mot de passe, double authentification |
| `/admin/api` | API de contenu | clé d'API portant un rôle |

Le dossier de cache, la base de données et les fichiers internes de Cockpit ne sont pas
accessibles depuis le web.

## HTTPS

Toutes les adresses sont redirigées en HTTPS de façon permanente (301) : l'administration
transmet un mot de passe et une clé d'API accompagne chaque lecture de contenu.

- Les hébergements qui terminent le TLS en amont d'Apache sont pris en compte via
  `X-Forwarded-Proto`, ce qui évite une boucle de redirection.
- `localhost`, `127.0.0.1` et les domaines en `.local` / `.test` sont laissés en clair, pour
  que le développement fonctionne sans certificat.
- `Strict-Transport-Security` n'est envoyé qu'en HTTPS : l'annoncer sur une connexion en clair
  n'a pas de sens et enfermerait un site pas encore servi en TLS.

## Protection de l'administration

**Choix retenu : la double authentification native de Cockpit.** Elle est intégrée au socle
(code à usage unique, application d'authentification), ce qui a été vérifié avant l'écriture
du code. Aucune couche d'authentification serveur supplémentaire n'est donc nécessaire.

À activer **compte par compte**, dès l'installation :

1. Se connecter à `/admin`
2. Menu du compte → *Account*
3. Activer *Two-factor authentication* et scanner le code affiché
4. Conserver le secret hors ligne

> Cockpit n'offre pas de moyen d'imposer la double authentification à tous les comptes. Sur une
> installation à un ou deux comptes, cela se vérifie à l'œil ; c'est à contrôler lors de chaque
> intervention.

Anonyme, `/admin` ne répond jamais 200 : il redirige vers le formulaire de connexion.

## Politique de mot de passe

Cockpit n'en propose aucune. Elle est ajoutée par l'extension `PasswordPolicy`, installée dans
`public/admin/addons/` — une extension, et non une modification de Cockpit, pour qu'une mise à
jour ne l'efface pas.

**La règle :**

- 12 caractères au minimum ;
- entre 12 et 15 caractères, mêler au moins trois sortes de caractères parmi minuscules,
  majuscules, chiffres et signes ;
- **à partir de 16 caractères, une phrase suffit** — la longueur est ce qui résiste réellement ;
- refus des mots de passe très courants, des suites de caractères identiques, et de tout mot de
  passe contenant le nom du compte ou l'adresse e-mail.

La règle est appliquée **côté serveur**, seul endroit qui compte : elle ne peut pas être
contournée en modifiant la page. Elle couvre **les deux chemins** qui fixent un mot de passe —
le formulaire de compte dans l'administration, et le lien de réinitialisation envoyé par
e-mail. Ne couvrir que le premier l'aurait rendue évitable.

Un indicateur de force accompagne la saisie dans l'administration, à titre indicatif seulement,
et n'apparaît jamais sur le formulaire de connexion : commenter un mot de passe existant
n'aiderait que quelqu'un regardant par-dessus l'épaule.

## Clés d'API

Chaque clé porte un **rôle**, et le rôle définit ce que la clé autorise. Il n'existe pas de clé
générale.

- **`Site public`** — créée à l'installation, en **lecture seule** sur l'identité, les pages et
  le menu. C'est la clé inscrite dans le `.env` du site.
- **Écriture** — aucune clé d'écriture n'est créée par défaut : une clé sans usage est une
  surface d'attaque de plus. Il faut en créer une lorsqu'un besoin apparaît :

```bash
php bin/cockpit-cle.php --nom="Formulaire de contact" --ecriture=messages
```

La clé obtenue ne peut alors écrire **que** dans la collection nommée, et ne peut pas même lire
les autres.

### Vérifier qu'une clé de lecture ne peut rien écrire

```bash
CLE=$(grep '^COCKPIT_API_KEY=' .env | cut -d= -f2)

# lecture : 200 attendu
curl -s -o /dev/null -w '%{http_code}\n' -H "api-key: $CLE" \
  https://domaine.tld/admin/api/content/items/pages

# écriture : 403 attendu
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H "api-key: $CLE" \
  -H 'Content-Type: application/json' -d '{"data":{"titre":"x","slug":"x"}}' \
  https://domaine.tld/admin/api/content/item/pages
```

## En-têtes de sécurité

| En-tête | Valeur | Portée |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'` et dérivés, `frame-ancestors 'none'` | site public |
| `X-Frame-Options` | `DENY` | site public |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HTTPS uniquement |
| `X-Content-Type-Options` | `nosniff` | partout |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | partout |
| `Permissions-Policy` | caméra, micro, position, paiement, USB refusés | partout |

La politique de contenu est stricte parce que **le site public ne charge rien d'ailleurs** :
ni police distante, ni image de tiers, ni mesure d'audience. L'annoncer transforme tout écart
futur en requête bloquée plutôt qu'en fuite silencieuse.

**L'administration en est exclue** : l'interface de Cockpit repose sur des scripts et des
styles en ligne, et son durcissement relève de Cockpit, pas de ce fichier.

## Secrets et données

- Aucune clé ni mot de passe dans le dépôt : `.env`, la base et l'installation de Cockpit sont
  exclus du suivi de version.
- La base SQLite est dans `var/data`, **hors de la racine web** : elle reste inatteignable même
  si une règle de réécriture venait à disparaître.
- La clé interne de Cockpit est générée par installation. Son absence empêche le démarrage
  plutôt que de retomber sur une valeur par défaut.

## À vérifier sur chaque installation

```bash
curl -sI https://domaine.tld/admin | head -1          # 302, jamais 200
curl -sI http://domaine.tld/ | head -2                # 301 vers https
curl -sI https://domaine.tld/ | grep -i content-security
curl -s https://domaine.tld/admin/storage/            # jamais de liste de fichiers
```

Puis, dans l'administration : double authentification active sur chaque compte, et aucune clé
d'API dont l'usage n'est pas connu.
