const SWAPPED = ['ws-rail-content', 'ws-stage'];
const LEAVE_MS = 140;
const HEADING_OFFSET = 104;

/**
 * Opens workspace pages without a page load, so that the assistant keeps its
 * conversation, even mid-answer, and the background keeps moving. Every failure
 * falls back to an ordinary navigation.
 */
export function createNavigation({ onChange, reducedMotion, scroller }) {
    let latest = 0;

    function scrollToTarget(hash, { immediate }) {
        const element = hash ? document.getElementById(hash.slice(1)) : null;

        if (!element) {
            scroller.to(0, { immediate: true });

            return;
        }

        const isPassage = element.classList.contains('passage');
        const { top, height } = element.getBoundingClientRect();
        const offset = isPassage ? (window.innerHeight - height) / 2 : HEADING_OFFSET;

        scroller.to(Math.max(0, window.scrollY + top - offset), { immediate });

        if (isPassage) {
            element.classList.remove('is-cited');
            void element.offsetWidth;
            element.classList.add('is-cited');
        }
    }

    function isSameBot(page) {
        return page.getElementById('ws-assistant')?.dataset.bot === document.getElementById('ws-assistant')?.dataset.bot;
    }

    async function visit(href, { push = true } = {}) {
        const url = new URL(href, window.location.href);
        const request = ++latest;
        const stage = document.getElementById('ws-stage');

        stage.classList.add('is-leaving');

        try {
            const [response] = await Promise.all([
                fetch(url.pathname + url.search, { headers: { Accept: 'text/html' } }),
                new Promise((resolve) => setTimeout(resolve, reducedMotion ? 0 : LEAVE_MS)),
            ]);

            if (!response.ok) {
                throw new Error(`The page answered ${response.status}.`);
            }

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');

            if (request !== latest) {
                return;
            }

            if (!isSameBot(page)) {
                throw new Error('The page belongs to another assistant.');
            }

            SWAPPED.forEach((id) => document.getElementById(id).replaceWith(document.adoptNode(page.getElementById(id))));
            document.title = page.title;

            if (push) {
                window.history.pushState({}, '', url);
            }

            onChange();
            scrollToTarget(url.hash, { immediate: true });
            document.getElementById('ws-stage').focus({ preventScroll: true });
        } catch {
            window.location.assign(url);
        }
    }

    /**
     * Opens a link, which may be a place in the page that is already open.
     */
    function open(href) {
        const url = new URL(href, window.location.href);

        if (url.pathname === window.location.pathname) {
            window.history.replaceState({}, '', url);
            scrollToTarget(url.hash, { immediate: false });
        } else {
            visit(url.href);
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[data-soft]');

        if (link && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            open(link.href);
        }
    });

    window.addEventListener('popstate', () => visit(window.location.href, { push: false }));

    if (window.location.hash) {
        requestAnimationFrame(() => scrollToTarget(window.location.hash, { immediate: true }));
    }

    return { open };
}
