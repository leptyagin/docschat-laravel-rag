import { createApp } from 'vue';
import App from '../widget/App.vue';
import { createField } from './field/field.js';
import { createNavigation } from './navigation.js';
import { createPanels } from './panels.js';
import { createSectionTracker } from './reader.js';
import { createScroller } from './scroller.js';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const isCompact = window.matchMedia('(max-width: 767px), (pointer: coarse)').matches;

const canvas = document.getElementById('ws-field');
const field = createField(canvas, { isCompact, isStill: reducedMotion });

if (!field) {
    canvas.remove();
}

const topbar = document.querySelector('.ws-topbar');
const scroller = createScroller({ reducedMotion });
const panels = createPanels({ onCovered: scroller.setPaused });
const sections = createSectionTracker(topbar);

let isScrollQueued = false;

/**
 * A short page must not race through the whole composition, so progress is
 * measured against at least this many screens of scrolling.
 */
const MIN_SCREENS = 1.8;

function applyScroll() {
    isScrollQueued = false;

    const scrolled = window.scrollY;
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;

    field?.setProgress(Math.min(1, scrolled / Math.max(scrollable, window.innerHeight * MIN_SCREENS)));
    topbar.style.setProperty('--read', scrollable > 0 ? String(Math.min(1, scrolled / scrollable)) : '0');

    sections.update();

    if (!reducedMotion) {
        document.querySelector('[data-parallax]')?.style.setProperty('--drift', `${Math.min(scrolled, 900)}px`);
    }
}

function queueScroll() {
    if (!isScrollQueued) {
        isScrollQueued = true;
        requestAnimationFrame(applyScroll);
    }
}

function onPageChange() {
    sections.refresh();
    scroller.refresh();
    panels.sync();
    field?.setSeed(Number(document.getElementById('ws-stage').dataset.fieldSeed));
    queueScroll();
}

const navigation = createNavigation({ onChange: onPageChange, reducedMotion, scroller });

window.addEventListener('scroll', queueScroll, { passive: true });
window.addEventListener('resize', queueScroll, { passive: true });

if (!reducedMotion && !isCompact) {
    window.addEventListener('pointermove', (event) => {
        field?.setPointer(event.clientX / window.innerWidth - 0.5, 0.5 - event.clientY / window.innerHeight);
    }, { passive: true });
}

const configElement = document.getElementById('docschat-config');

if (configElement) {
    const chat = createApp(App, {
        config: JSON.parse(configElement.textContent),
        onOpenSource(url) {
            panels.showDocument();
            navigation.open(url);
        },
        onHide: () => panels.hideAssistant(),
        onBusyChange(isBusy) {
            field?.setEnergy(isBusy ? 1 : 0);
            document.getElementById('ws-assistant').classList.toggle('is-busy', isBusy);
        },
    }).mount('#docschat-app');

    document.addEventListener('click', (event) => {
        const question = event.target.closest?.('[data-ask]');
        const draft = event.target.closest?.('[data-prefill]');

        if (question) {
            panels.showAssistant();
            chat.ask(question.dataset.ask);
        } else if (draft) {
            panels.showAssistant();
            chat.prefill(draft.dataset.prefill);
        }
    });
}

onPageChange();
