#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate the snowflake-php README diagrams.

Reads every label table in scripts/i18n/labels.<lang>.json and writes
docs/i18n/img/<lang>/{pet,architecture,features,lifecycle}.svg

    python3 scripts/generate-diagrams.py            # all languages
    python3 scripts/generate-diagrams.py en zh-CN   # selected languages

Verify a change by rasterizing and looking at the result:

    rsvg-convert -b white docs/i18n/img/en/architecture.svg -o /tmp/a.png

Notes for editors:
  * SVG collapses runs of whitespace inside <text>, so never rely on double
    spaces for alignment - use a separator like " · " instead.
  * Labels that are too wide for their box are auto-shrunk by fit(), but long
    translations still read better when they are short. Keep them short.
"""
import glob
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LABEL_DIR = os.path.join(ROOT, "scripts", "i18n")
OUT_DIR = os.path.join(ROOT, "docs", "i18n", "img")

FONT = ("-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', "
        "'PingFang SC', 'Hiragino Sans', 'Yu Gothic', 'Malgun Gothic', "
        "Roboto, 'Noto Sans CJK SC', 'Source Han Sans SC', 'Microsoft YaHei', "
        "'Kohinoor Devanagari', 'Devanagari Sangam MN', 'Nirmala UI', "
        "'Noto Sans Devanagari', 'Bangla MN', 'Bangla Sangam MN', 'Noto Sans Bengali', "
        "'Geeza Pro', 'Noto Sans Arabic', Arial, sans-serif")
MONO = ("'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, "
        "'Courier New', monospace")

INK   = "#0f1e33"; BODY = "#3d4d66"; MUTED = "#6b7c96"; FAINT = "#93a3b8"
LINE  = "#dbe6f5"; RULE = "#e8eefa"
BLUE  = "#2563eb"; BLUE_T = "#eef4ff"; BLUE_B = "#c3d7ff"
CYAN  = "#0e7490"; CYAN_T = "#e8f7fb"; CYAN_B = "#a9dcea"
VIOLET = "#6d28d9"; VIOLET_T = "#f4efff"; VIOLET_B = "#d5c6fa"
AMBER = "#b45309"; AMBER_T = "#fff6e6"; AMBER_B = "#f7d59b"
RED   = "#dc2626"; RED_T  = "#fdeeee"; RED_B   = "#f4c2c2"
GREEN = "#0f766e"; GREEN_T = "#e9f7f3"; GREEN_B = "#a9dcd0"
SLATE = "#475569"; SLATE_T = "#f4f7fb"; SLATE_B = "#dbe3ec"
ARROW = "#93a6bf"; ARROW_HI = "#5b8def"


# ---------------------------------------------------------------- text metrics

def is_cjk(ch):
    return ord(ch) >= 0x2E80


def tw(s, size, weight="400"):
    """Rough advance width in px. Deliberately conservative: over-estimating
    only costs a little padding, under-estimating overflows a box."""
    w = 0.0
    for ch in s:
        o = ord(ch)
        if o >= 0x2E80:                      # CJK, kana, hangul: full width
            w += size * 1.0
        elif 0x0900 <= o <= 0x097F or 0x0980 <= o <= 0x09FF:
            w += size * 0.66                 # Devanagari / Bengali
        elif 0x0600 <= o <= 0x06FF or 0x0750 <= o <= 0x077F:
            w += size * 0.52                 # Arabic
        elif 0x0370 <= o <= 0x04FF:
            w += size * 0.56                 # Greek / Cyrillic
        elif ch in "iljItf.,:;'|!()[]":
            w += size * 0.32
        elif ch in "mMW@":
            w += size * 0.86
        elif ch.isupper() or ch.isdigit():
            w += size * 0.62
        else:
            w += size * 0.53
    if weight in ("600", "700"):
        w *= 1.05
    return w


def fit(s, size, maxw, weight="400"):
    """Shrink the font size just enough for a single-line label to fit."""
    w = tw(s, size, weight)
    if w <= maxw:
        return size
    return max(7.5, size * maxw / w)


def wrap(s, size, maxw, weight="400"):
    """Greedy wrap. CJK breaks anywhere; space-separated scripts stay whole."""
    units, buf = [], ""
    for ch in s:
        if is_cjk(ch) or ch == " ":
            if buf:
                units.append(buf)
                buf = ""
            units.append(ch)
        else:
            buf += ch
    if buf:
        units.append(buf)

    lines, cur = [], ""
    for u in units:
        if u == " ":
            if cur and not cur.endswith(" "):
                cur += " "
            continue
        if cur and not cur.endswith(" ") and not is_cjk(cur[-1]) and not is_cjk(u[0]):
            trial = cur + " " + u
        else:
            trial = cur + u
        if tw(trial.rstrip(), size, weight) <= maxw:
            cur = trial
        else:
            if cur.strip():
                lines.append(cur.strip())
            cur = u
    if cur.strip():
        lines.append(cur.strip())
    return lines


# ------------------------------------------------------------------ primitives

def esc(s):
    return html.escape(str(s), quote=True)


def text(x, y, s, size=13, fill=BODY, weight="400", anchor="start",
         family=None, opacity=None, spacing=None):
    a = f' text-anchor="{anchor}"' if anchor != "start" else ""
    f = f' font-family="{family}"' if family else ""
    o = f' opacity="{opacity}"' if opacity else ""
    sp = f' letter-spacing="{spacing}"' if spacing else ""
    return (f'<text x="{x}" y="{y}" font-size="{size:.1f}" fill="{fill}" '
            f'font-weight="{weight}"{a}{f}{o}{sp}>{esc(s)}</text>')


def block(x, y, lines, size=11.5, fill=BODY, weight="400", anchor="start", lh=None):
    lh = lh if lh else size * 1.5
    return "\n".join(text(x, y + i * lh, l, size, fill, weight, anchor)
                     for i, l in enumerate(lines))


def tline(x, y, parts, size=12, anchor="start"):
    """Inline runs: [(text, fill, weight)]"""
    tspans = "".join(f'<tspan fill="{c}" font-weight="{w}">{esc(t)}</tspan>'
                     for t, c, w in parts)
    return (f'<text x="{x}" y="{y}" font-size="{size}" fill="{BODY}" '
            f'text-anchor="{anchor}">{tspans}</text>')


def rect(x, y, w, h, fill="#fff", stroke=None, rx=10, sw=1.5, dash=None):
    s = f' stroke="{stroke}" stroke-width="{sw}"' if stroke else ""
    d = f' stroke-dasharray="{dash}"' if dash else ""
    return (f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" rx="{rx}" '
            f'fill="{fill}"{s}{d}/>')


def line(x1, y1, x2, y2, color=ARROW, sw=2, dash=None, cap="round"):
    d = f' stroke-dasharray="{dash}"' if dash else ""
    return (f'<line x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}" '
            f'stroke="{color}" stroke-width="{sw}" stroke-linecap="{cap}"{d}/>')


def arrow(x1, y1, x2, y2, color=ARROW, sw=2, dash=None, head=None):
    m = f' marker-end="url(#mk-{(head or color.lstrip("#"))})"'
    da = f' stroke-dasharray="{dash}"' if dash else ""
    return (f'<path d="M{x1:.1f},{y1:.1f} L{x2:.1f},{y2:.1f}" fill="none" '
            f'stroke="{color}" stroke-width="{sw}" stroke-linecap="round"{da}{m}/>')


def diamond(cx, cy, w, h, fill="#fff", stroke=BLUE, sw=1.8):
    pts = f"{cx},{cy - h / 2} {cx + w / 2},{cy} {cx},{cy + h / 2} {cx - w / 2},{cy}"
    return f'<polygon points="{pts}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>'


def pill(x, y, w, h, label, fill, size=12, tcolor="#fff", weight="600", maxw=None):
    if maxw and w > maxw:
        x += (w - maxw) / 2
        w = maxw
    size = fit(label, size, w - 16, weight)
    return (rect(x, y, w, h, fill, None, h / 2)
            + text(x + w / 2, y + h / 2 + size * 0.36, label, size, tcolor,
                   weight, "middle"))


def card(x, y, w, h, title, body_lines, accent=BLUE, tint="#fff", border=None,
         tsize=13, bsize=11.5, tag=None, tagfill=None, pad=15):
    border = border or LINE
    out = [rect(x, y, w, h, tint, border, 12),
           rect(x, y, 4, h, accent, None, 2),
           text(x + pad, y + 24, title, fit(title, tsize, w - pad * 2, "700"),
                INK, "700")]
    ly = y + 46
    if tag:
        twd = tw(tag, 10.5, "600") + 16
        out.append(rect(x + pad, y + 32, twd, 18, tagfill or BLUE_T, None, 9))
        out.append(text(x + pad + twd / 2, y + 45, tag, 10.5, accent, "600", "middle"))
        ly = y + 66
    if body_lines:
        lines = []
        for para in body_lines:
            lines.extend(wrap(para, bsize, w - pad * 2))
        out.append(block(x + pad, ly, lines, bsize, BODY))
    return "\n".join(out)


def defs(body):
    used = sorted(set(re.findall(r'url\(#mk-([0-9a-fA-F]{6})\)', body)))
    out = ['<defs>']
    for cid in used:
        out.append(
            f'<marker id="mk-{cid}" viewBox="0 0 10 10" refX="8.5" refY="5" '
            f'markerWidth="6.5" markerHeight="6.5" orient="auto">'
            f'<path d="M0,0.6 L9.5,5 L0,9.4 z" fill="#{cid}"/></marker>')
    out.append('</defs>')
    return "\n".join(out)


def svg(w, h, body, title, desc):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" '
            f'width="{w}" height="{h}" role="img" font-family="{FONT}">\n'
            f'<title>{esc(title)}</title>\n<desc>{esc(desc)}</desc>\n'
            f'{defs(body)}\n<rect width="{w}" height="{h}" fill="#ffffff"/>\n'
            f'{body}\n</svg>\n')


def header(w, title, sub, badge="snowflake-php"):
    out = [text(40, 54, title, fit(title, 27, w - 300, "700"), INK, "700"),
           text(40, 82, sub, fit(sub, 13.5, w - 300), MUTED),
           line(40, 100, w - 40, 100, RULE, 1.5, cap="butt")]
    bw = tw(badge, 11.5, "600") + 26
    out.append(rect(w - 40 - bw, 36, bw, 26, BLUE_T, BLUE_B, 13, 1.2))
    out.append(text(w - 40 - bw / 2, 53, badge, 11.5, BLUE, "600", "middle"))
    return "\n".join(out)


# --------------------------------------------------------------------- mascot

def pet(L):
    W, H = 320, 340
    cx, cy = 160, 152
    arms = []
    for i in range(6):
        arms.append(f'<g transform="rotate({i * 60} {cx} {cy})">'
                    f'{line(cx + 46, cy, cx + 112, cy, BLUE, 8)}'
                    f'{line(cx + 72, cy, cx + 94, cy - 20, CYAN, 5)}'
                    f'{line(cx + 72, cy, cx + 94, cy + 20, CYAN, 5)}'
                    f'{line(cx + 96, cy, cx + 114, cy - 16, "#8ecdf0", 4)}'
                    f'{line(cx + 96, cy, cx + 114, cy + 16, "#8ecdf0", 4)}'
                    f'</g>')

    def sparkle(x, y, r, o):
        return (f'<path d="M{x},{y - r} L{x + r * 0.26},{y - r * 0.26} L{x + r},{y} '
                f'L{x + r * 0.26},{y + r * 0.26} L{x},{y + r} L{x - r * 0.26},{y + r * 0.26} '
                f'L{x - r},{y} L{x - r * 0.26},{y - r * 0.26} Z" fill="{BLUE}" opacity="{o}"/>')

    face = "\n".join([
        f'<circle cx="{cx}" cy="{cy}" r="42" fill="#ffffff" stroke="#8ecdf0" stroke-width="3"/>',
        f'<circle cx="{cx - 15}" cy="{cy - 7}" r="5" fill="{INK}"/>',
        f'<circle cx="{cx + 15}" cy="{cy - 7}" r="5" fill="{INK}"/>',
        f'<circle cx="{cx - 13.2}" cy="{cy - 8.8}" r="1.7" fill="#ffffff"/>',
        f'<circle cx="{cx + 16.8}" cy="{cy - 8.8}" r="1.7" fill="#ffffff"/>',
        f'<path d="M{cx - 14},{cy + 11} Q{cx},{cy + 23} {cx + 14},{cy + 11}" fill="none" '
        f'stroke="{INK}" stroke-width="3.4" stroke-linecap="round"/>',
        f'<ellipse cx="{cx - 27}" cy="{cy + 8}" rx="6.5" ry="4.2" fill="#ffb3c7" opacity="0.75"/>',
        f'<ellipse cx="{cx + 27}" cy="{cy + 8}" rx="6.5" ry="4.2" fill="#ffb3c7" opacity="0.75"/>',
    ])

    tagline = L("pet.tagline")
    body = "\n".join([
        '<rect width="320" height="340" rx="26" fill="#f6fbff"/>',
        '<path d="M0,26 A26,26 0 0 1 26,0 H294 A26,26 0 0 1 320,26 V150 H0 Z" fill="#e8f4ff"/>',
        f'<circle cx="{cx}" cy="{cy}" r="118" fill="#d8ecff" opacity="0.5"/>',
        '<g opacity="0.9">' + sparkle(56, 78, 11, 0.55) + sparkle(266, 104, 8, 0.45)
        + sparkle(66, 232, 7, 0.4) + sparkle(272, 214, 10, 0.5) + '</g>',
        '<g><animateTransform attributeName="transform" type="translate" '
        'values="0 0; 0 -7; 0 0" dur="4.5s" repeatCount="indefinite" '
        'calcMode="spline" keyTimes="0;0.5;1" keySplines="0.42 0 0.58 1;0.42 0 0.58 1"/>'
        + "".join(arms) + face + '</g>',
        text(cx, 306, "snowflake-php", 25, BLUE, "700", "middle", spacing="0.4"),
        text(cx, 328, tagline, fit(tagline, 12.5, 280, "400"), MUTED, "400", "middle"),
    ])
    return svg(W, H, body, "snowflake-php — " + tagline, tagline)


# --------------------------------------------------------------- architecture

def architecture(L):
    W, H = 980, 958
    body = [header(W, L("arch.title"), L("arch.subtitle"))]

    def band(y, h, key, color, tint):
        label = L(key)
        out = [rect(40, y, W - 80, h, tint, LINE, 14, 1.4)]
        lw = min(tw(label, 12, "600") + 30, 340)
        out.append(pill(58, y - 13, lw, 26, label, color, 12))
        return "\n".join(out)

    body.append(band(116, 88, "arch.band.app", BLUE, "#f8fbff"))
    bw = (W - 80 - 48 - 48) / 4
    for i, (k, ks) in enumerate([("arch.app1", "arch.app1.sub"), ("arch.app2", "arch.app2.sub"),
                                 ("arch.app3", "arch.app3.sub"), ("arch.app4", "arch.app4.sub")]):
        x = 64 + i * (bw + 16)
        body.append(rect(x, 142, bw, 52, "#ffffff", BLUE_B, 10))
        body.append(text(x + bw / 2, 165, L(k), fit(L(k), 13, bw - 16, "600"), INK, "600", "middle"))
        body.append(text(x + bw / 2, 183, L(ks), fit(L(ks), 11, bw - 16), MUTED, "400", "middle"))
    body.append(arrow(490, 204, 490, 226, ARROW_HI, 2, head="5b8def"))
    body.append(text(500, 220, L("arch.arrow.instance"), fit(L("arch.arrow.instance"), 11, 400), MUTED))

    adp = [("arch.ad1", "arch.ad1.sub1", "arch.ad1.sub2"), ("arch.ad2", "arch.ad2.sub1", "arch.ad2.sub2"),
           ("arch.ad3", "arch.ad3.sub1", "arch.ad3.sub2"), ("arch.ad4", "arch.ad4.sub1", "arch.ad4.sub2")]
    body.append(band(232, 108, "arch.band.adapter", BLUE, "#f8fbff"))
    for i, (k, k1, k2) in enumerate(adp):
        x = 64 + i * (bw + 16)
        body.append(rect(x, 258, bw, 64, "#ffffff", BLUE_B, 10))
        body.append(text(x + 14, 279, L(k), fit(L(k), 12.5, bw - 24, "700"), BLUE, "700"))
        body.append(text(x + 14, 297, L(k1), fit(L(k1), 10.5, bw - 24), BODY))
        body.append(text(x + 14, 313, L(k2), fit(L(k2), 10.5, bw - 24), MUTED))
    body.append(arrow(490, 340, 490, 362, ARROW_HI, 2, head="5b8def"))
    body.append(text(500, 356, L("arch.arrow.config"), 11, MUTED, family=MONO))

    body.append(band(368, 192, "arch.band.core", BLUE, "#f3f8ff"))
    body.append(text(64, 398, L("arch.core.title"), fit(L("arch.core.title"), 12.5, W - 200, "700"),
                     INK, "700"))
    core = [("arch.core1", "arch.core1.sub"), ("arch.core2", "arch.core2.sub"),
            ("arch.core3", "arch.core3.sub"), ("arch.core4", "arch.core4.sub"),
            ("arch.core5", "arch.core5.sub"), ("arch.core6", "arch.core6.sub")]
    cw = (W - 80 - 48 - 32) / 3
    for i, (k, ks) in enumerate(core):
        x = 64 + (i % 3) * (cw + 16)
        y = 412 + (i // 3) * 74
        body.append(rect(x, y, cw, 58, "#ffffff", BLUE_B, 10))
        body.append(text(x + 14, y + 25, L(k), fit(L(k), 12.5, cw - 24, "600"), INK, "600"))
        body.append(text(x + 14, y + 45, L(ks), fit(L(ks), 10.5, cw - 24), MUTED))
    body.append(arrow(490, 560, 490, 582, ARROW_HI, 2, head="5b8def"))
    body.append(text(500, 576, L("arch.arrow.next"), fit(L("arch.arrow.next"), 11, 400), MUTED))

    body.append(band(588, 152, "arch.band.contract", CYAN, "#f6fdfe"))
    body.append(rect(64, 612, 300, 104, "#ffffff", CYAN_B, 10))
    body.append(text(80, 638, "SequenceResolver", 13, CYAN, "700", family=MONO))
    body.append(text(80, 658, L("arch.iface.sub"), fit(L("arch.iface.sub"), 10.5, 268), MUTED))
    body.append(text(80, 682, "next(int $timestamp,", 10.5, BODY, family=MONO))
    body.append(text(80, 698, "     int $maxSequence): ?int", 10.5, BODY, family=MONO))
    for i, (k, ks) in enumerate([("arch.impl1", "arch.impl1.sub"), ("arch.impl2", "arch.impl2.sub"),
                                 ("arch.impl3", "arch.impl3.sub")]):
        y = 612 + i * 40
        body.append(rect(400, y, 540, 32, "#ffffff", CYAN_B, 8))
        body.append(arrow(400, y + 16, 368, y + 16, CYAN, 1.8, head="0e7490"))
        name, sub = L(k), L(ks)
        size = min(fit(name + sub, 11.5, 512, "700"), 11.5)
        body.append(tline(414, y + 21, [(name, CYAN, "700"), (sub, MUTED, "400")], size))
    body.append(text(356, 668, L("arch.implements"), 10, FAINT, "600", "end"))

    body.append(band(768, 150, "arch.band.cross", SLATE, "#fbfcfe"))
    body.append(text(64, 800, L("arch.exc.title"), 12, INK, "700"))
    exs = [("SnowflakeException " + L("arch.exc.base"), SLATE, SLATE_T),
           ("ClockDriftException", RED, RED_T),
           ("TimestampOverflowException", AMBER, AMBER_T),
           ("InvalidWorkerIdException", AMBER, AMBER_T),
           ("InvalidDatacenterIdException", AMBER, AMBER_T)]
    px, py = 64, 812
    for name, c, t in exs:
        w = tw(name, 10.5, "600") + 22
        if px + w > 560:
            px, py = 64, py + 26
        body.append(rect(px, py, w, 22, t, None, 11))
        body.append(text(px + w / 2, py + 15, name, 10.5, c, "600", "middle"))
        px += w + 8
    body.append(line(608, 792, 608, 898, LINE, 1.5, cap="butt"))
    body.append(text(632, 800, L("arch.cfg.title"), 12, INK, "700"))
    cfg = []
    for k in ("arch.cfg1", "arch.cfg2", "arch.cfg3"):
        cfg.extend(wrap(L(k), 11, 288))
    body.append(block(632, 822, cfg, 11 if len(cfg) <= 4 else 10, BODY, lh=15))
    return svg(W, H, "\n".join(body), L("arch.title"), L("arch.subtitle"))


# ------------------------------------------------------------------- features

def features(L):
    W, H = 980, 640
    body = [header(W, L("feat.title"), L("feat.subtitle"))]
    groups = [
        ("feat.g1", BLUE, "#f8fbff", BLUE_B, [1, 2, 3]),
        ("feat.g2", CYAN, "#f6fdfe", CYAN_B, [4, 5, 6]),
        ("feat.g3", VIOLET, "#fbf9ff", VIOLET_B, [7, 8, 9]),
    ]
    pw = (W - 80 - 40) / 3
    for gi, (gkey, color, tint, border, cards) in enumerate(groups):
        x = 40 + gi * (pw + 20)
        name = L(gkey)
        body.append(rect(x, 120, pw, 476, tint, LINE, 14, 1.4))
        body.append(pill(x + 16, 136, min(tw(name, 12.5, "700") + 28, pw - 32), 28,
                         name, color, 12.5, maxw=pw - 32))
        for ci, n in enumerate(cards):
            y = 184 + ci * 138
            body.append(card(x + 16, y, pw - 32, 124, L(f"feat.c{n}"), [L(f"feat.c{n}.desc")],
                             color, "#ffffff", border, 13, 11.5, L(f"feat.c{n}.tag"), tint))
    body.append(text(40, 632, L("feat.footer"), fit(L("feat.footer"), 11.5, W - 80), MUTED))
    return svg(W, H, "\n".join(body), L("feat.title"), L("feat.subtitle"))


# ------------------------------------------------------------------ lifecycle

def lifecycle(L):
    W, H = 980, 942
    CX, RX, RW = 350, 650, 290
    body = [header(W, L("life.title"), L("life.subtitle"))]

    body.append(card(RX, 120, RW, 186, L("life.bits.title"), None, BLUE, "#fff", BLUE_B))
    segs = [(1, SLATE, ""), (41, BLUE, L("life.bits.seg.timestamp")), (5, VIOLET, ""),
            (5, CYAN, ""), (12, AMBER, "")]
    bx, by, bh = RX + 16, 160, 26
    avail = RW - 40
    for i, (bits, c, lab) in enumerate(segs):
        w = max(9, avail * bits / 64)
        body.append(rect(bx, by, w, bh, c, None, 4 if i in (0, 4) else 0))
        if lab:
            body.append(text(bx + w / 2, by + 18, lab, fit(lab, 10, w - 4, "600"),
                             "#fff", "600", "middle"))
        bx += w
    bits = []
    for k in ("life.bits.l1", "life.bits.l2", "life.bits.l3"):
        bits.extend(wrap(L(k), 10.5, 258))
    body.append(block(RX + 16, 208, bits, 10.5 if len(bits) <= 5 else 9.5, MUTED, lh=15))

    body.append(card(RX, 324, RW, 204, L("life.state.title"),
                     [L("life.state.l1"), L("life.state.l2"), L("life.state.l3")],
                     VIOLET, "#fbf9ff", VIOLET_B))
    note = wrap(L("life.state.note"), 10.5, RW - 40)
    body.append(block(RX + 16, 486, note, 10.5 if len(note) <= 3 else 9.5, RED, "600",
                      lh=(10.5 if len(note) <= 3 else 9.5) * 1.35))

    body.append(card(RX, 546, RW, 162, L("life.cap.title"),
                     [L("life.cap.l1"), L("life.cap.l2"), L("life.cap.l3")],
                     CYAN, "#f6fdfe", CYAN_B))

    body.append(card(RX, 726, RW, 182, L("life.time.title"), None, AMBER, "#fffaf0", AMBER_B))
    tx, ty, twd = RX + 20, 780, RW - 40
    body.append(rect(tx, ty, twd, 10, SLATE_B, None, 5))
    body.append(rect(tx, ty, twd * 0.035, 10, AMBER, None, 5))
    body.append(line(tx + twd * 0.035, ty - 4, tx + twd * 0.035, ty + 15, AMBER, 2, cap="butt"))
    body.append(text(tx, ty - 9, L("life.time.epoch"), fit(L("life.time.epoch"), 10, 120, "600"),
                     AMBER, "600"))
    body.append(text(tx + twd, ty - 9, L("life.time.limit"), fit(L("life.time.limit"), 10, 120, "600"),
                     MUTED, "600", "end"))
    tl = []
    for k in ("life.time.l1", "life.time.l2", "life.time.l3", "life.time.l4"):
        tl.extend(wrap(L(k), 10.5, 248))
    body.append(block(tx, ty + 36, tl, 10.5, BODY, lh=16))
    body.append(tline(tx, ty + 48 + 16 * len(tl),
                      [(L("life.time.now"), MUTED, "400")], 10.5))

    state = {"y": 120, "prev": None}

    def flow(label, caption=None, dia=False, sub=None, alabel=None):
        y, prev = state["y"], state["prev"]
        if prev is not None:
            body.append(arrow(CX, prev, CX, y, ARROW_HI, 2, head="5b8def"))
            if alabel:
                body.append(text(CX + 12, (prev + y) / 2 + 4, alabel, 10.5, MUTED, "600"))
        h = 68 if dia else 50
        if dia:
            body.append(diamond(CX, y + h / 2, 300, h, "#fff", BLUE, 1.8))
            lines = 2 if sub else 1
            y0 = y + h / 2 + 5 - (lines - 1) * 8
            body.append(text(CX, y0, label, fit(label, 12.5, 210 if sub else 250, "600"),
                             INK, "600", "middle"))
            if sub:
                body.append(text(CX, y0 + 16, sub, fit(sub, 10.5, 160), MUTED, "400", "middle"))
        else:
            body.append(rect(CX - 170, y, 340, h, "#fff", BLUE_B, 10))
            body.append(text(CX, y + h / 2 + 4.5, label, fit(label, 12.5, 320, "600"),
                             INK, "600", "middle"))
        bottom = y + h
        if caption:
            lines = wrap(caption, 10.5, 344)
            body.append(block(CX - 172, bottom + 19, lines, 10.5, AMBER, lh=16))
            bottom += 19 + 16 * (len(lines) - 1)
        state["y"], state["prev"] = bottom + 24, bottom

    body.append(pill(CX - 110, 120, 220, 36, L("life.flow.start"), GREEN, 12.5))
    state["y"], state["prev"] = 180, 156

    flow(L("life.flow.n1"), L("life.flow.n1.cap"))
    flow(L("life.flow.d1"), L("life.flow.d1.cap"), dia=True, alabel=L("life.flow.no"))
    flow(L("life.flow.n2"), L("life.flow.n2.cap"))
    flow(L("life.flow.d2"), L("life.flow.d2.cap"), dia=True, sub=L("life.flow.d2.sub"),
         alabel=L("life.flow.no"))
    flow(L("life.flow.n3"), L("life.flow.n3.cap"))
    flow(L("life.flow.n4"), L("life.flow.n4.cap"))

    yy = state["y"]
    body.append(arrow(CX, state["prev"], CX, yy, ARROW_HI, 2, head="5b8def"))
    body.append(pill(CX - 170, yy, 340, 38, L("life.flow.end"), GREEN, 12.5))
    return svg(W, H, "\n".join(body), L("life.title"), L("life.subtitle"))


# ------------------------------------------------------------------- driver

def load_labels(lang):
    path = os.path.join(LABEL_DIR, f"labels.{lang}.json")
    with open(path, encoding="utf-8") as f:
        data = json.load(f)
    meta = data.pop("_meta")

    def L(key, _d=data, _lang=lang):
        try:
            return _d[key]
        except KeyError:
            raise SystemExit(f"labels.{_lang}.json is missing key: {key}")

    return meta, L


def main(argv):
    langs = argv[1:] or sorted(
        os.path.basename(p)[len("labels."):-len(".json")]
        for p in glob.glob(os.path.join(LABEL_DIR, "labels.*.json")))

    need = set(re.findall(r'L\("([^"]+)"\)', open(__file__, encoding="utf-8").read()))
    problems, total = [], 0
    for lang in langs:
        meta, L = load_labels(lang)
        missing = need - set(json.load(open(os.path.join(LABEL_DIR, f"labels.{lang}.json"),
                                            encoding="utf-8")))
        if missing:
            problems.append(f"{lang}: {', '.join(sorted(missing))}")
            continue

        out = os.path.join(OUT_DIR, lang)
        os.makedirs(out, exist_ok=True)
        for name, fn in (("pet", pet), ("architecture", architecture),
                         ("features", features), ("lifecycle", lifecycle)):
            with open(os.path.join(out, f"{name}.svg"), "w", encoding="utf-8") as f:
                f.write(fn(L))
            total += 1
        print(f"  {lang:6s} {meta['name']:16s} 4 diagrams")

    if problems:
        raise SystemExit("incomplete label tables:\n  " + "\n  ".join(problems))
    print(f"wrote {total} SVG files to {os.path.relpath(OUT_DIR, ROOT)}")


if __name__ == "__main__":
    main(sys.argv)
