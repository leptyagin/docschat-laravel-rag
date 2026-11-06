const DOCKED = '(min-width: 1024px)';
const RAIL_FIXED = '(min-width: 1280px)';
const STORAGE_KEY = 'docschat:workspace:assistant';

function remembered() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function remember(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // The preference simply does not survive a reload.
    }
}

/**
 * The parts of the workspace that open and close: the assistant, docked on a
 * large screen and a full view on a small one, and the library rail.
 */
export function createPanels({ onCovered }) {
    const body = document.body;
    const assistant = document.getElementById('ws-assistant');
    const rail = document.getElementById('ws-rail');
    const stage = () => document.getElementById('ws-stage');
    const docked = window.matchMedia(DOCKED);
    const railFixed = window.matchMedia(RAIL_FIXED);
    const toggles = document.querySelectorAll('[data-assistant-toggle]');
    const railToggles = document.querySelectorAll('[data-rail-toggle]');
    const viewButtons = document.querySelectorAll('[data-view-button]');

    function sync() {
        const isAssistantView = body.dataset.view === 'assistant';
        const isOpen = assistant?.dataset.open === 'true';

        viewButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.viewButton === body.dataset.view)));
        toggles.forEach((button) => button.setAttribute('aria-expanded', String(isOpen)));

        if (assistant) {
            assistant.inert = docked.matches ? !isOpen : !isAssistantView;
        }

        stage().inert = !docked.matches && isAssistantView;

        const isRailOpen = rail?.dataset.open === 'true';

        if (rail) {
            rail.inert = !railFixed.matches && !isRailOpen;
        }

        railToggles.forEach((button) => button.setAttribute('aria-expanded', String(isRailOpen)));
        body.classList.toggle('has-drawer', isRailOpen && !railFixed.matches);
        onCovered((isAssistantView && !docked.matches) || (isRailOpen && !railFixed.matches));
    }

    function setAssistantOpen(isOpen) {
        assistant.dataset.open = String(isOpen);
        body.dataset.assistant = isOpen ? 'open' : 'closed';
        remember(isOpen ? 'open' : 'closed');
        sync();
    }

    function setView(view) {
        body.dataset.view = view;
        sync();
    }

    function setRailOpen(isOpen) {
        if (rail) {
            rail.dataset.open = String(isOpen);
            sync();
        }
    }

    /**
     * Brings the assistant into view, whichever form it has on this screen.
     */
    function showAssistant() {
        if (docked.matches) {
            setAssistantOpen(true);
        } else {
            setView('assistant');
        }
    }

    function showDocument() {
        if (!docked.matches) {
            setView('document');
        }
    }

    toggles.forEach((button) => button.addEventListener('click', () => setAssistantOpen(assistant.dataset.open !== 'true')));
    railToggles.forEach((button) => button.addEventListener('click', () => setRailOpen(rail.dataset.open !== 'true')));
    viewButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.viewButton)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && rail?.dataset.open === 'true') {
            setRailOpen(false);
            railToggles[0]?.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-rail-close]') || (rail?.dataset.open === 'true' && event.target.closest?.('#ws-rail a'))) {
            setRailOpen(false);
        }
    });

    docked.addEventListener('change', sync);
    railFixed.addEventListener('change', sync);

    if (assistant && remembered() === 'closed' && docked.matches) {
        assistant.dataset.open = 'false';
        body.dataset.assistant = 'closed';
    }

    sync();

    return { hideAssistant: () => (docked.matches ? setAssistantOpen(false) : setView('document')), showAssistant, showDocument, sync };
}
