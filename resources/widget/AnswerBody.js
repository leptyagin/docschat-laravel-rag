import { computed, defineComponent, h } from 'vue';
import { parseMarkdown } from './markdown.js';

/**
 * Shows an answer. Text only ever reaches the page as text nodes, and a link
 * keeps its address only if the parser accepted its scheme.
 */
export default defineComponent({
    name: 'AnswerBody',
    props: {
        text: { type: String, required: true },
        sources: { type: Array, default: () => [] },
        sourceLink: { type: Function, required: true },
    },
    setup(props) {
        const blocks = computed(() => parseMarkdown(props.text));

        function citation(number) {
            const source = props.sources.find((candidate) => candidate.number === number);
            const link = source ? props.sourceLink(source) : null;

            if (!link?.href) {
                return h('span', { class: 'chat__cite', title: source?.title }, String(number));
            }

            return h('a', { class: 'chat__cite', ...link, title: source.title, 'aria-label': `Source ${number}: ${source.title}` }, String(number));
        }

        function inline(nodes) {
            return nodes.map((node) => {
                switch (node.type) {
                    case 'code':
                        return h('code', node.text);
                    case 'strong':
                        return h('strong', inline(node.children));
                    case 'em':
                        return h('em', inline(node.children));
                    case 'link':
                        return h('a', { href: node.href, target: '_blank', rel: 'noopener noreferrer' }, inline(node.children));
                    case 'cite':
                        return citation(node.number);
                    default:
                        return node.text;
                }
            });
        }

        function table(block) {
            return h('div', { class: 'chat__table', tabindex: 0 }, [
                h('table', [
                    h('thead', h('tr', block.head.map((cell) => h('th', { scope: 'col' }, inline(cell))))),
                    h('tbody', block.rows.map((row) => h('tr', row.map((cell) => h('td', inline(cell)))))),
                ]),
            ]);
        }

        function block(node) {
            switch (node.type) {
                case 'pre':
                    return h('pre', { tabindex: 0 }, h('code', node.text));
                case 'heading':
                    return h('p', { class: 'chat__heading' }, inline(node.children));
                case 'rule':
                    return h('hr');
                case 'quote':
                    return h('blockquote', node.children.map(block));
                case 'list':
                    return h(node.isOrdered ? 'ol' : 'ul', node.items.map((item) => h('li', inline(item))));
                case 'table':
                    return table(node);
                default:
                    return h('p', inline(node.children));
            }
        }

        return () => h('div', { class: 'chat__prose' }, blocks.value.map(block));
    },
});
