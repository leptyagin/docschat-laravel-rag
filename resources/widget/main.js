import { mountApp } from './app.js';
import { mountLauncher } from './launcher.js';

/**
 * One file with two roles. On a customer's site it adds the launcher, and inside
 * the widget's own iframe, where the page provides a configuration, it runs the chat.
 */
const configElement = document.getElementById('docschat-config');

if (configElement) {
    mountApp(JSON.parse(configElement.textContent));
} else if (document.currentScript) {
    mountLauncher(document.currentScript);
}
