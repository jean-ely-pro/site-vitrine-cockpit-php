/**
 * Says, while a colour is being picked, whether it will be readable.
 *
 * A colour chosen for its looks is often unreadable: pale pink on white sits
 * around 3,5:1 where 4,5:1 is required. Saying so at the moment of choosing
 * costs nothing; discovering it after the site is online costs a redesign.
 *
 * Advisory here — the site refuses on its own to apply a colour that does not
 * reach the threshold, and falls back on its default.
 */

const MINIMUM = 4.5;
const WHITE = '#ffffff';

const STYLES = `
.contraste { margin-top: .4rem; font-size: .8125rem; line-height: 1.45; }
.contraste__valeur { font-weight: 600; }
.contraste.ok { color: #1e7e34; }
.contraste.insuffisant { color: #c0392b; }
`;

function luminance(hex) {
    const value = hex.replace('#', '');

    const channels = [0, 2, 4].map((offset) => {
        const c = parseInt(value.substr(offset, 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(a, b) {
    const first = luminance(a);
    const second = luminance(b);
    const [high, low] = first > second ? [first, second] : [second, first];

    return (high + 0.05) / (low + 0.05);
}

function isColour(value) {
    return /^#[0-9a-f]{6}$/i.test((value || '').trim());
}

function verdict(value) {
    const ratio = contrast(value, WHITE);
    const rounded = ratio.toFixed(1).replace('.', ',');

    return ratio >= MINIMUM
        ? { className: 'ok', text: `Contraste sur blanc : ${rounded}:1 — lisible.` }
        : {
              className: 'insuffisant',
              text:
                  `Contraste sur blanc : ${rounded}:1 — insuffisant, il en faut 4,5:1. ` +
                  'Choisir une teinte plus foncée. En l’état, le site gardera sa couleur par défaut.',
          };
}

function annotate(input) {
    let notice = input.parentNode.querySelector('.contraste');

    if (!isColour(input.value)) {
        if (notice) notice.remove();
        return;
    }

    if (!notice) {
        notice = document.createElement('div');
        notice.className = 'contraste';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        input.parentNode.appendChild(notice);
    }

    const { className, text } = verdict(input.value.trim());
    notice.className = `contraste ${className}`;
    notice.textContent = text;
}

function scan() {
    document.querySelectorAll('input[type="color"], input[type="text"]').forEach((input) => {
        // Only the colour fields: a colour picker, or a text field holding one.
        if (input.type !== 'color' && !isColour(input.value)) return;

        if (!input.dataset.contraste) {
            input.dataset.contraste = '1';
            input.addEventListener('input', () => annotate(input));
        }

        annotate(input);
    });
}

const style = document.createElement('style');
style.textContent = STYLES;
document.head.appendChild(style);

new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
scan();
