/**
 * Marks the section being read in the navigation: the last heading that has
 * passed the top bar. Above the first heading nothing is marked.
 */
export function createSectionTracker(topbar) {
    let entries = [];
    let current = null;

    function mark(link) {
        if (link !== current) {
            current?.classList.remove('is-current');
            link?.classList.add('is-current');
            current = link;
        }
    }

    return {
        /** Call after the page content changes. */
        refresh() {
            current = null;
            entries = [...document.querySelectorAll('[data-section-link]')]
                .map((link) => ({ link, heading: document.getElementById(link.hash.slice(1)) }))
                .filter((entry) => entry.heading);
        },

        update() {
            const line = topbar.getBoundingClientRect().bottom + 32;

            mark(entries.findLast((entry) => entry.heading.getBoundingClientRect().top <= line)?.link ?? null);
        },
    };
}
