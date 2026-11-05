/**
 * Browsers may refuse storage inside a third-party iframe, in which case the
 * conversation simply does not survive a page reload.
 */
export function load(key) {
    try {
        return JSON.parse(sessionStorage.getItem(key));
    } catch {
        return null;
    }
}

export function save(key, value) {
    try {
        sessionStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Nothing to do: see above.
    }
}
