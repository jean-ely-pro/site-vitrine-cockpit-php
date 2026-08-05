/**
 * Flags images heavy enough to slow a page down.
 *
 * Cockpit already shows weight, format and dimensions; what it does not say
 * is when a figure is a problem. A visitor on a phone pays for every extra
 * kilobyte, so the threshold is stated out loud rather than left to judgement.
 *
 * Advisory only — nothing is refused. The site serves lighter copies anyway;
 * this simply avoids keeping a needlessly heavy original around.
 */

const HEAVY_BYTES = 600 * 1024;
const WIDE_PIXELS = 2600;

const STYLES = `
.media-alerte {
    margin-top: .5rem;
    padding: .5rem .75rem;
    border-radius: 4px;
    border-left: 3px solid #b8860b;
    background: rgba(184, 134, 11, .12);
    font-size: .8125rem;
    line-height: 1.45;
}
`;

function readableSize(bytes) {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} Mo`
        : `${Math.round(bytes / 1024)} ko`;
}

function warningFor(asset) {
    const reasons = [];

    if (asset.size > HEAVY_BYTES) {
        reasons.push(`elle pèse ${readableSize(asset.size)}`);
    }

    if (asset.width > WIDE_PIXELS) {
        reasons.push(`elle fait ${asset.width} pixels de large`);
    }

    if (!reasons.length) return null;

    return (
        `Image lourde : ${reasons.join(' et ')}. ` +
        'Le site en sert des copies allégées, mais une image de départ plus légère ' +
        '— environ 2000 pixels de large — accélère l’administration et les sauvegardes.'
    );
}

/** The asset dialog shows weight and dimensions; the notice goes under them. */
function annotate() {
    document.querySelectorAll('[data-media-asset]').forEach((node) => {
        node.remove();
    });

    const app = document.querySelector('kiss-dialog') || document;

    app.querySelectorAll('.kiss-text-monospace').forEach((node) => {
        const match = node.textContent.trim().match(/^(\d+)x(\d+)$/);
        if (!match) return;

        const container = node.parentNode;
        if (!container || container.querySelector('.media-alerte')) return;

        const sizeNode = [...container.querySelectorAll('.kiss-text-monospace')].find((n) =>
            /\d\s*(ko|kb|mo|mb)/i.test(n.textContent),
        );

        const bytes = sizeNode ? parseSize(sizeNode.textContent) : 0;
        const message = warningFor({ size: bytes, width: Number(match[1]) });

        if (!message) return;

        const notice = document.createElement('div');
        notice.className = 'media-alerte';
        notice.dataset.mediaAsset = '1';
        notice.textContent = message;
        container.appendChild(notice);
    });
}

function parseSize(text) {
    const match = text.trim().match(/([\d.,]+)\s*(ko|kb|mo|mb|o|b)/i);
    if (!match) return 0;

    const value = parseFloat(match[1].replace(',', '.'));
    const unit = match[2].toLowerCase();

    if (unit === 'mo' || unit === 'mb') return value * 1024 * 1024;
    if (unit === 'ko' || unit === 'kb') return value * 1024;

    return value;
}

const style = document.createElement('style');
style.textContent = STYLES;
document.head.appendChild(style);

new MutationObserver(annotate).observe(document.documentElement, { childList: true, subtree: true });
annotate();
