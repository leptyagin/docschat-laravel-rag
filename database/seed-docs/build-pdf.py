"""Builds a seed PDF from Markdown: python3 build-pdf.py source.md target.pdf (standard library only)."""
import re, sys, textwrap, zlib

W, H, M = 595, 842, 60
STYLES = {1: ('F2', 20, 34), 2: ('F2', 14, 26), 3: ('F2', 12, 22), 0: ('F1', 10.5, 14)}

def esc(t):
    t = t.replace('\\', r'\\').replace('(', r'\(').replace(')', r'\)')
    return t.encode('cp1252', 'replace')

def plain(t):
    return re.sub(r'\*\*(.+?)\*\*', r'\1', re.sub(r'\*(.+?)\*', r'\1', t))

def layout(md):
    pages, cur, y = [], [], H - M
    def put(font, size, lead, text):
        nonlocal cur, y
        if y - lead < M:
            pages.append(cur); cur, y = [], H - M
        y -= lead
        cur.append((font, size, M, y, text))
    def gap(n):
        nonlocal y
        y -= n
    blocks = re.split(r'\n\s*\n', md.strip())
    for b in blocks:
        b = b.strip()
        m = re.match(r'^(#{1,3})\s+(.*)$', b)
        if m:
            lvl = len(m.group(1))
            f, s, l = STYLES[lvl]
            if y - l - 60 < M:
                pages.append(cur); cur, y = [], H - M
            gap(16); put(f, s, l, plain(m.group(2))); gap(16)
            continue
        f, s, l = STYLES[0]
        for line in b.split('\n'):
            line = plain(line.strip())
            if line.startswith('|'):
                if set(line) <= set('|-: '):
                    continue
                line = ' | '.join(c.strip() for c in line.strip('|').split('|'))
            for w in textwrap.wrap(line, 92) or ['']:
                put(f, s, l, w)
        gap(12)
    pages.append(cur)
    return pages

def build(pages):
    objs = {1: b'<< /Type /Catalog /Pages 2 0 R >>',
            3: b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4: b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'}
    kids, n = [], 5
    for p in pages:
        s = b'BT\n'
        for f, size, x, y, t in p:
            s += b'/%s %s Tf 1 0 0 1 %d %.1f Tm (' % (f.encode(), str(size).encode(), x, y) + esc(t) + b') Tj\n'
        s += b'ET'
        z = zlib.compress(s)
        objs[n] = b'<< /Length %d /Filter /FlateDecode >>\nstream\n' % len(z) + z + b'\nendstream'
        objs[n + 1] = (b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents %d 0 R '
                       b'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>' % (W, H, n))
        kids.append(n + 1); n += 2
    objs[2] = b'<< /Type /Pages /Kids [%s] /Count %d >>' % (b' '.join(b'%d 0 R' % k for k in kids), len(kids))
    out, offs = bytearray(b'%PDF-1.4\n'), {}
    for i in sorted(objs):
        offs[i] = len(out)
        out += b'%d 0 obj\n' % i + objs[i] + b'\nendobj\n'
    xref = len(out); total = max(objs) + 1
    out += b'xref\n0 %d\n0000000000 65535 f \n' % total
    for i in range(1, total):
        out += b'%010d 00000 n \n' % offs[i]
    out += b'trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n' % (total, xref)
    return bytes(out)

src, dst = sys.argv[1], sys.argv[2]
open(dst, 'wb').write(build(layout(open(src, encoding='utf-8').read())))
