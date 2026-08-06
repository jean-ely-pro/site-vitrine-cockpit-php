/**
 * Adds a « Envoyer un message test » button above the received messages.
 *
 * Checking that messages arrive is easy — they are on screen. Checking that
 * the e-mail notification leaves is not, and that is the part that fails
 * quietly on shared hosting.
 */

const ROUTE = '/contact/message-test';
const LABEL = 'Envoyer un message test';

const STYLES = `
.contact-test { margin: 0 0 1rem; }
.contact-test__retour { margin-top: .5rem; font-size: .875rem; line-height: 1.45; }
.contact-test__retour.ok { color: #1e7e34; }
.contact-test__retour.souci { color: #b8860b; }
`;

function baseUrl() {
    // The admin may live under a subfolder; its own links say where.
    const link = document.querySelector('a[href$="/content"], a[href*="/content/"]');
    if (!link) return '';

    const href = new URL(link.href, location.origin).pathname;
    return href.slice(0, href.indexOf('/content'));
}

function insert() {
    if (!location.pathname.includes('/content/collection/messages')) return;
    if (document.querySelector('.contact-test')) return;

    const anchor = document.querySelector('main, .kiss-container, app-content');
    if (!anchor) return;

    const box = document.createElement('div');
    box.className = 'contact-test';
    box.innerHTML = `<button type="button" class="kiss-button kiss-button-primary">${LABEL}</button>
        <div class="contact-test__retour" role="status" aria-live="polite"></div>`;

    anchor.prepend(box);

    const button = box.querySelector('button');
    const feedback = box.querySelector('.contact-test__retour');

    button.addEventListener('click', async () => {
        button.disabled = true;
        feedback.className = 'contact-test__retour';
        feedback.textContent = 'Envoi en cours…';

        try {
            const response = await fetch(`${baseUrl()}${ROUTE}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();

            feedback.textContent = result.texte || 'Réponse inattendue.';
            feedback.classList.add(result.ok && result.envoye ? 'ok' : 'souci');
        } catch (error) {
            feedback.textContent = `Le test n’a pas abouti : ${error.message}`;
            feedback.classList.add('souci');
        } finally {
            button.disabled = false;
        }
    });
}

const style = document.createElement('style');
style.textContent = STYLES;
document.head.appendChild(style);

new MutationObserver(insert).observe(document.documentElement, { childList: true, subtree: true });
insert();
