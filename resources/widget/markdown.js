/**
 * A small Markdown reader for answers. It returns a tree of plain objects that
 * AnswerBody turns into Vue nodes, so model output never becomes HTML, and it
 * accepts text that is still streaming, such as a code fence not closed yet.
 */
const SAFE_LINK = /^(https?:|mailto:)/i;

const INLINE = new RegExp(
    [
        '`(?<code>[^`\\n]+)`',
        '\\*\\*(?<strong>[^\\n]+?)\\*\\*',
        '(?<![\\w*])\\*(?<em>[^\\s*](?:[^*\\n]*[^\\s*])?)\\*(?![\\w*])',
        '\\[(?<label>[^\\]\\n]+)\\]\\((?<href>[^)\\s]+)\\)',
        '\\[(?<cite>\\d+(?:\\s*,\\s*\\d+)*)\\]',
        '(?<url>https?:\\/\\/[^\\s<>()]*[^\\s<>().,;:!?])',
        '(?<email>[\\w.+-]+@[\\w-]+(?:\\.[\\w-]+)+)',
    ].join('|'),
    'g',
);

const FENCE = /^\s*(```|~~~)\s*([\w+-]*)\s*$/;
const HEADING = /^\s{0,3}(#{1,6})\s+(.+?)\s*#*$/;
const RULE = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/;
const QUOTE = /^\s{0,3}>\s?(.*)$/;
const LIST_ITEM = /^\s{0,3}([-*+]|\d{1,3}[.)])\s+(.*)$/;
const TABLE_DIVIDER = /^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/;

export function parseInline(text) {
    const nodes = [];
    let cursor = 0;

    for (const match of text.matchAll(INLINE)) {
        const groups = match.groups;

        if (match.index > cursor) {
            nodes.push({ type: 'text', text: text.slice(cursor, match.index) });
        }

        cursor = match.index + match[0].length;

        if (groups.code !== undefined) {
            nodes.push({ type: 'code', text: groups.code });
        } else if (groups.strong !== undefined) {
            nodes.push({ type: 'strong', children: parseInline(groups.strong) });
        } else if (groups.em !== undefined) {
            nodes.push({ type: 'em', children: parseInline(groups.em) });
        } else if (groups.href !== undefined) {
            nodes.push(
                SAFE_LINK.test(groups.href)
                    ? { type: 'link', href: groups.href, children: parseInline(groups.label) }
                    : { type: 'text', text: groups.label },
            );
        } else if (groups.cite !== undefined) {
            groups.cite.split(',').forEach((number) => nodes.push({ type: 'cite', number: Number(number) }));
        } else if (groups.url !== undefined) {
            nodes.push({ type: 'link', href: groups.url, children: [{ type: 'text', text: groups.url }] });
        } else {
            nodes.push({ type: 'link', href: `mailto:${groups.email}`, children: [{ type: 'text', text: groups.email }] });
        }
    }

    if (cursor < text.length) {
        nodes.push({ type: 'text', text: text.slice(cursor) });
    }

    return nodes;
}

function tableCells(line) {
    return line
        .trim()
        .replace(/^\|/, '')
        .replace(/\|$/, '')
        .split('|')
        .map((cell) => parseInline(cell.trim()));
}

function startsBlock(line, next) {
    return FENCE.test(line) || HEADING.test(line) || RULE.test(line) || QUOTE.test(line) || LIST_ITEM.test(line) || isTableStart(line, next);
}

function isTableStart(line, next) {
    return line.includes('|') && next !== undefined && next.includes('-') && TABLE_DIVIDER.test(next);
}

export function parseMarkdown(source) {
    const lines = source.replace(/\r\n?/g, '\n').split('\n');
    const blocks = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];
        const next = lines[index + 1];

        if (line.trim() === '') {
            index++;
        } else if (FENCE.test(line)) {
            const [, marker, language] = FENCE.exec(line);
            const code = [];

            index++;

            while (index < lines.length && !lines[index].trim().startsWith(marker)) {
                code.push(lines[index++]);
            }

            index++;
            blocks.push({ type: 'pre', language, text: code.join('\n') });
        } else if (HEADING.test(line)) {
            const [, marks, text] = HEADING.exec(line);

            index++;
            blocks.push({ type: 'heading', level: marks.length, children: parseInline(text) });
        } else if (RULE.test(line)) {
            index++;
            blocks.push({ type: 'rule' });
        } else if (QUOTE.test(line)) {
            const quoted = [];

            while (index < lines.length && QUOTE.test(lines[index])) {
                quoted.push(QUOTE.exec(lines[index++])[1]);
            }

            blocks.push({ type: 'quote', children: parseMarkdown(quoted.join('\n')) });
        } else if (LIST_ITEM.test(line)) {
            const isOrdered = /\d/.test(LIST_ITEM.exec(line)[1]);
            const items = [];

            while (index < lines.length) {
                const item = LIST_ITEM.exec(lines[index]);

                if (item) {
                    items.push(item[2]);
                } else if (lines[index].trim() !== '' && /^\s+/.test(lines[index])) {
                    items[items.length - 1] += ` ${lines[index].trim()}`;
                } else if (lines[index].trim() !== '' || !LIST_ITEM.test(lines[index + 1] ?? '')) {
                    break;
                }

                index++;
            }

            blocks.push({ type: 'list', isOrdered, items: items.map(parseInline) });
        } else if (isTableStart(line, next)) {
            const rows = [];

            index += 2;

            while (index < lines.length && lines[index].includes('|')) {
                rows.push(tableCells(lines[index++]));
            }

            blocks.push({ type: 'table', head: tableCells(line), rows });
        } else {
            const paragraph = [line];

            index++;

            while (index < lines.length && lines[index].trim() !== '' && !startsBlock(lines[index], lines[index + 1])) {
                paragraph.push(lines[index++]);
            }

            blocks.push({ type: 'paragraph', children: parseInline(paragraph.join('\n')) });
        }
    }

    return blocks;
}
