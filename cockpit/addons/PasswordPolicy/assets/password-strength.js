/**
 * Strength meter shown next to password fields in the admin.
 *
 * Only guidance: the rule that decides is applied on the server, in
 * Policy.php. Kept deliberately in step with it.
 *
 * Not shown on the sign-in form — there, the password already exists and
 * commenting on it would only help someone looking over a shoulder.
 */

const MINIMUM = 12;
const PASSPHRASE = 16;

const LEVELS = [
    { label: 'Trop court', className: 'faible' },
    { label: 'Faible', className: 'faible' },
    { label: 'Correct', className: 'correct' },
    { label: 'Solide', className: 'solide' },
];

const STYLES = `
.mdp-force { margin-top: .4rem; font-size: .8125rem; }
.mdp-force__barre { height: 4px; border-radius: 2px; background: rgba(127,127,127,.25); overflow: hidden; }
.mdp-force__jauge { height: 100%; width: 0; transition: width .2s ease; }
.mdp-force__jauge.faible { background: #c0392b; }
.mdp-force__jauge.correct { background: #b8860b; }
.mdp-force__jauge.solide { background: #1e7e34; }
.mdp-force__texte { margin-top: .2rem; }
`;

function varieties(value) {
    return [/\p{Ll}/u, /\p{Lu}/u, /\d/, /[^\p{L}\d]/u].filter((r) => r.test(value)).length;
}

/** Same ladder as the server rule: length first, variety only below a passphrase. */
function score(value) {
    const length = [...value].length;

    if (length === 0) return null;
    if (length < MINIMUM) return 0;
    if (length >= PASSPHRASE) return varieties(value) >= 2 ? 3 : 2;

    return varieties(value) >= 3 ? 2 : 1;
}

function attach(input) {
    if (input.dataset.mdpForce) return;
    input.dataset.mdpForce = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'mdp-force';
    wrapper.innerHTML =
        '<div class="mdp-force__barre"><div class="mdp-force__jauge"></div></div>' +
        '<div class="mdp-force__texte" role="status" aria-live="polite"></div>';

    input.insertAdjacentElement('afterend', wrapper);

    const gauge = wrapper.querySelector('.mdp-force__jauge');
    const text = wrapper.querySelector('.mdp-force__texte');

    const update = () => {
        const level = score(input.value);

        if (level === null) {
            gauge.style.width = '0';
            text.textContent = '';
            return;
        }

        const { label, className } = LEVELS[level];
        gauge.style.width = `${((level + 1) / LEVELS.length) * 100}%`;
        gauge.className = `mdp-force__jauge ${className}`;
        text.textContent =
            level === 0
                ? `${label} — au moins ${MINIMUM} caractères`
                : level === 1
                  ? `${label} — mêler minuscules, majuscules, chiffres et signes, ou allonger`
                  : label;
    };

    input.addEventListener('input', update);
    update();
}

function scan() {
    // Account pages only: never the sign-in form.
    if (!location.pathname.includes('/system/users')) return;

    document.querySelectorAll('input[type="password"]').forEach(attach);
}

const style = document.createElement('style');
style.textContent = STYLES;
document.head.appendChild(style);

// The admin renders its forms after loading, so watch for them to appear.
new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
scan();
