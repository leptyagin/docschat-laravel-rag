import { readableOn, validColor } from './color.js';

const WIDE_SCREEN = '(min-width: 640px)';

const STYLES = `
    :host { all: initial; }

    .launcher {
        position: fixed;
        bottom: 20px;
        z-index: 2147483000;
        display: grid;
        place-items: center;
        width: 56px;
        height: 56px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: var(--accent);
        color: var(--on-accent);
        box-shadow: 0 6px 20px rgb(0 0 0 / 25%);
        cursor: pointer;
    }

    .launcher:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 3px;
    }

    .panel {
        position: fixed;
        bottom: 88px;
        z-index: 2147483000;
        display: none;
        width: min(400px, calc(100vw - 40px));
        height: min(640px, calc(100vh - 108px));
        overflow: hidden;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 12px 40px rgb(0 0 0 / 30%);
    }

    .right { right: 20px; }
    .left { left: 20px; }
    .panel.open { display: block; }

    iframe {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
    }

    @media (max-width: 639px) {
        .panel {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
        }

        .launcher.open { display: none; }
    }
`;

const CHAT_ICON = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';

const CLOSE_ICON = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';

/**
 * Adds the chat to any page. The launcher sits in a shadow root and the chat in an
 * iframe, so that the styles of the host page and the widget cannot affect each other.
 */
export function mountLauncher(script) {
    const { bot, color, position, greeting, open } = script.dataset;

    if (!bot || window.__docschatMounted) {
        return;
    }

    window.__docschatMounted = true;

    const origin = new URL(script.src).origin;
    const accent = validColor(color);
    const side = position === 'left' ? 'left' : 'right';

    const host = document.createElement('div');
    const root = host.attachShadow({ mode: 'open' });

    host.style.setProperty('--accent', accent);
    host.style.setProperty('--on-accent', readableOn(accent));

    const style = document.createElement('style');
    style.textContent = STYLES;

    const launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = `launcher ${side}`;

    const panel = document.createElement('div');
    panel.className = `panel ${side}`;

    let iframe = null;

    const frameUrl = () => {
        const url = new URL(`/widget/${encodeURIComponent(bot)}`, origin);

        if (color) {
            url.searchParams.set('color', accent);
        }

        if (greeting) {
            url.searchParams.set('greeting', greeting);
        }

        return url.href;
    };

    const setOpen = (isOpen) => {
        panel.classList.toggle('open', isOpen);
        launcher.classList.toggle('open', isOpen);
        launcher.innerHTML = isOpen ? CLOSE_ICON : CHAT_ICON;
        launcher.setAttribute('aria-label', isOpen ? 'Close chat' : 'Open chat');
        launcher.setAttribute('aria-expanded', String(isOpen));

        if (isOpen && !iframe) {
            iframe = document.createElement('iframe');
            iframe.title = 'Chat';
            iframe.src = frameUrl();
            panel.append(iframe);
        }

        if (isOpen) {
            iframe.contentWindow?.postMessage({ type: 'docschat:focus' }, origin);
        } else {
            launcher.focus({ preventScroll: true });
        }
    };

    launcher.addEventListener('click', () => setOpen(!panel.classList.contains('open')));

    window.addEventListener('message', (event) => {
        if (iframe && event.origin === origin && event.source === iframe.contentWindow && event.data?.type === 'docschat:close') {
            setOpen(false);
        }
    });

    root.append(style, panel, launcher);
    document.body.append(host);

    setOpen(open === 'true' && window.matchMedia(WIDE_SCREEN).matches);
}
