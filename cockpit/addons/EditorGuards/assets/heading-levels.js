/**
 * Hides the heading levels the site does not use from the text editor.
 *
 * A page has exactly one level-one heading — its title — and goes no deeper
 * than level three. Only levels two and three are therefore offered.
 *
 * Courtesy only: the rule is applied when content is saved, in Headings.php,
 * so text pasted from a word processor is corrected all the same.
 */

const ALLOWED = ['format_h2', 'format_h3'];
const HEADING_ICON = /^format_h[1-6]$/;

/** The format menu: list entries carrying a heading icon. */
function trimFormatMenu(root) {
    root.querySelectorAll('kiss-popout li a icon').forEach((icon) => {
        const name = icon.textContent.trim();

        if (HEADING_ICON.test(name) && !ALLOWED.includes(name)) {
            const entry = icon.closest('li');
            if (entry) entry.hidden = true;
        }
    });
}

/** The floating menu above a selection: buttons labelled H1, H2, H3. */
function trimFloatingMenu(root) {
    root.querySelectorAll('button.kiss-button-small').forEach((button) => {
        if (button.textContent.trim() === 'H1') button.hidden = true;
    });
}

function scan() {
    trimFormatMenu(document);
    trimFloatingMenu(document);
}

new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
scan();
