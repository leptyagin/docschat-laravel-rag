import { createApp } from 'vue';
import App from './App.vue';
import styles from './widget.css?inline';
import { readableOn, validColor } from './color.js';

export function mountApp(config) {
    const style = document.createElement('style');
    style.textContent = styles;
    document.head.append(style);

    const accent = validColor(config.color);

    document.documentElement.style.setProperty('--accent', accent);
    document.documentElement.style.setProperty('--on-accent', readableOn(accent));

    createApp(App, { config }).mount('#docschat-app');
}
