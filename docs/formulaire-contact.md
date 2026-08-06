# Formulaire de contact

## Le chemin d'un message

1. Le visiteur remplit le formulaire, sur une page du site.
2. Il est envoyé à `/contact`, **sur le site lui-même** — rien ne part ailleurs.
3. Le message est déposé dans « Messages reçus », dans l'administration.
4. Le client reçoit un e-mail à l'adresse de l'identité du site.
5. Le visiteur est renvoyé sur sa page avec un mot de confirmation.

Le renvoi se fait par une adresse ordinaire : recharger la page ne renvoie pas le message une
seconde fois.

## Ce qui protège le formulaire

Aucun service tiers n'intervient — pas de captcha, donc rien de l'adresse du visiteur n'est
transmis à qui que ce soit pour recevoir un message. Trois contrôles s'en chargent :

| Contrôle | Ce qu'il attrape |
|---|---|
| Un champ que personne ne voit | les envois automatiques, qui remplissent tout |
| Le temps passé sur le formulaire | les envois instantanés, impossibles à la main |
| Une limite de 5 messages par heure et par adresse | les envois en rafale |

Un envoi automatique reçoit la même réponse qu'un envoi accepté : il n'apprend rien en
réessayant. Rien n'est enregistré pour autant.

**Le deuxième contrôle ne joue que sur une page rendue à la demande.** Dès qu'une page est
servie depuis le cache, l'horodatage qu'elle porte est celui de sa mise en cache, et le délai
mesuré ne veut plus rien dire — il passera toujours. Les deux autres ne dépendent pas du cache.

L'adresse du visiteur n'est jamais conservée : seule une empreinte sert à compter, et elle ne
dit rien de qui a écrit quoi. Ces compteurs sont effacés d'eux-mêmes passé une heure.

La limite vaut **par adresse**. Depuis un réseau d'entreprise, où plusieurs personnes sortent
par la même adresse, cinq messages en une heure peuvent donc être atteints à plusieurs. C'est
assumé pour un site vitrine ; la valeur se change dans `src/Contact/SpamGuard.php`.

## Consentement

La case **n'est jamais cochée d'avance** : un consentement que la personne n'a pas donné n'en
est pas un. Sans elle, le message n'est pas envoyé et la case est signalée.

Le formulaire renvoie à une **page de politique de confidentialité**, choisie dans le bloc au
moment de le poser. Ce lien est obligatoire : sans lui, le formulaire n'est pas conforme.

Le consentement est conservé avec le message, comme preuve.

## Les clés

Le site dépose les messages avec une clé qui **ne sait faire que cela** : elle ne peut pas les
relire, ni toucher au reste du contenu. C'est important — le formulaire est ouvert à tout le
monde.

Le client, lui, lit les messages, les coche comme traités et les supprime, mais n'en crée
jamais.

Sans `COCKPIT_WRITE_KEY` dans le `.env`, le formulaire **n'est pas affiché du tout** : les
coordonnées le remplacent. Mieux vaut pas de formulaire qu'un formulaire qui perd les messages
en silence.

## Vérifier que tout marche

Le point qui échoue en silence, c'est l'e-mail : les messages arrivent bien dans
l'administration, mais personne n'est prévenu, et cela ne se voit que le jour où un client se
plaint de n'avoir jamais eu de réponse.

Deux façons de le vérifier :

- **Dans l'administration** : ouvrir « Messages reçus », bouton *Envoyer un message test*.
- **En ligne de commande**, pendant une installation :

```bash
php bin/message-test.php
```

Les deux déposent un message et disent **si l'e-mail est parti**, ou pourquoi il n'est pas
parti.

## L'e-mail de notification

Envoyé depuis l'adresse de l'identité du site — donc du domaine du client, ce que les serveurs
destinataires acceptent — avec le visiteur en adresse de réponse : répondre à la notification
répond directement à la personne.

Le transport est la fonction d'envoi de PHP, ce que fournit un hébergement mutualisé. Pour
passer par un SMTP, compléter `mailer` dans `cockpit/config.php`.

Si l'identité du site n'a pas d'adresse e-mail, aucune notification n'est envoyée — les
messages restent consultables dans l'administration.

## Ajouter le formulaire à une page

*Contenu → Pages*, ouvrir la page, ajouter une section **Formulaire de contact**, puis
désigner la page de politique de confidentialité. Le titre et le texte d'introduction sont
libres.
