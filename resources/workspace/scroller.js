import Lenis from 'lenis';

/**
 * The elastic scrolling of the page. Lenis eases the wheel only, so touch stays
 * native, and a panel that can scroll keeps the wheel for itself. With reduced
 * motion the browser scrolls as usual.
 */
export function createScroller({ reducedMotion }) {
    const lenis = reducedMotion ? null : new Lenis({ lerp: 0.08, autoRaf: true, allowNestedScroll: true });

    return {
        /** Scrolls the page. Goes through Lenis, which would otherwise undo it. */
        to(top, { immediate = false } = {}) {
            if (lenis) {
                lenis.scrollTo(top, { immediate, force: true });
            } else {
                window.scrollTo({ top, behavior: 'instant' });
            }
        },

        /** Stops the page while something covers it. */
        setPaused(isPaused) {
            if (isPaused) {
                lenis?.stop();
            } else {
                lenis?.start();
            }
        },

        /** Measures again after the content of the page changes. */
        refresh() {
            lenis?.resize();
        },
    };
}
