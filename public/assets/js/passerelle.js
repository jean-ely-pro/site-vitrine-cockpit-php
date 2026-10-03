/* Site → admin (cockpit/addons/Passerelle): when the admin has set its « passerelle » cookie — in the
   signed-in person's browser only — an avatar with a green dot opens the admin in a new tab.
   Without that cookie, as for every visitor, nothing happens. No address of the admin is written here. */
(function () {
    'use strict';

    var brut = (document.cookie.match(/(?:^|;\s*)passerelle=([^;]+)/) || [])[1];
    if (!brut) {
        return;
    }

    var infos;
    try {
        infos = JSON.parse(decodeURIComponent(escape(atob(brut.replace(/-/g, '+').replace(/_/g, '/')))));
    } catch (e) {
        return;
    }
    if (!infos || typeof infos.admin !== 'string' || !/^(https?:\/\/[^\s"<>]+|\/[^\s"<>]*)$/.test(infos.admin)) {
        return;
    }

    var nom = String(infos.nom || 'Administration');

    function afficher() {
        var style = document.createElement('link');
        style.rel = 'stylesheet';
        style.href = '/assets/css/passerelle.css';
        document.head.appendChild(style);

        var lien = document.createElement('a');
        lien.className = 'passerelle';
        lien.href = infos.admin;
        lien.target = '_blank';
        lien.rel = 'noopener';
        lien.setAttribute('aria-label', 'Connecté en tant que ' + nom + ' : ouvrir l’administration dans un nouvel onglet');

        var avatar = document.createElement('span');
        avatar.className = 'passerelle__avatar';
        avatar.setAttribute('aria-hidden', 'true');
        // The account photo when there is one (Avatar addon), the initial otherwise.
        if (typeof infos.photo === 'string' && /^(https?:\/\/[^\s"<>]+|\/[^\s"<>]*)\.webp$/.test(infos.photo)) {
            var photo = document.createElement('img');
            photo.className = 'passerelle__photo';
            photo.src = infos.photo;
            photo.alt = '';
            avatar.appendChild(photo);
        } else {
            avatar.textContent = nom.trim().charAt(0).toUpperCase();
        }

        var pastille = document.createElement('span');
        pastille.className = 'passerelle__pastille';
        pastille.setAttribute('aria-hidden', 'true');
        avatar.appendChild(pastille);

        var texte = document.createElement('span');
        texte.className = 'passerelle__texte';
        texte.setAttribute('aria-hidden', 'true');
        var titre = document.createElement('b');
        titre.textContent = nom;
        texte.appendChild(titre);
        texte.appendChild(document.createTextNode('Administration'));

        lien.appendChild(avatar);
        lien.appendChild(texte);
        document.body.appendChild(lien);
    }

    function oublier() {
        document.cookie = 'passerelle=; path=/; max-age=0; samesite=lax';
    }

    // The addon is asked whether someone is still signed in; it answers the site's own address, even from
    // another origin. The avatar shows only on a clear yes. Anything else — signed out, the Passerelle addon
    // disabled or removed (404, or a request the browser blocks), the admin unreachable — and the avatar
    // stays hidden, its cookie forgotten (the admin sets it again on its next page).
    fetch(infos.admin.replace(/\/?$/, '/') + 'passerelle/etat', { credentials: 'include' })
        .then(function (r) {
            return r.ok ? r.json() : null;
        })
        .then(function (etat) {
            if (etat && etat.connecte === true) {
                afficher();
                return;
            }
            oublier();
        })
        .catch(oublier);
}());
