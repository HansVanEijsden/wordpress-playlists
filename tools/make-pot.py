#!/usr/bin/env python3
"""
Extract the plugin's translatable strings into languages/wordpress-playlists.pot.

Development tool. Run from the repository root:

    python3 tools/make-pot.py

The plural pairs are passed to _n() as variables from the views, so they are
listed explicitly below.
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
INCLUDES = ROOT / "includes"
OUT = ROOT / "languages" / "wordpress-playlists.pot"

TEMPLATE_STRINGS = [
    "Sidebar",
]

PLURALS = [
    ("%s track", "%s tracks"),
    ("%s broadcast", "%s broadcasts"),
    ("%s play", "%s plays"),
    ("%s artist", "%s artists"),
    ("%s result", "%s results"),
    ("in %s broadcast", "in %s broadcasts"),
]

STRING_RE = re.compile(
    r"""(?P<fn>esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_x)\(\s*'(?P<text>(?:\\.|[^'\\])*)'(?:\s*,\s*'(?P<context>(?:\\.|[^'\\])*)')?""",
    re.S,
)


def unescape(text: str) -> str:
    return text.replace("\\'", "'").replace("\\\\", "\\")


def po_escape(text: str) -> str:
    return text.replace("\\", "\\\\").replace('"', '\\"')


def main() -> None:
    singles: dict[tuple[str, str], None] = {}
    plurals: dict[tuple[str, str], None] = {}

    for path in sorted(INCLUDES.glob("*.php")):
        source = path.read_text(encoding="utf-8")

        for match in STRING_RE.finditer(source):
            text = unescape(match.group("text"))
            context = unescape(match.group("context") or "") if match.group("fn") == "_x" else ""
            singles[(context, text)] = None

    for text in TEMPLATE_STRINGS:
        singles[("", text)] = None

    for singular, plural in PLURALS:
        plurals[(singular, plural)] = None

    lines = [
        '# Copyright (C) 2026 Hans van Eijsden Consultancy',
        '# This file is distributed under the GPL-2.0-or-later license.',
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: WordPress Playlists 1.1.0\\n"',
        '"Report-Msgid-Bugs-To: https://github.com/HansVanEijsden/wordpress-playlists/issues\\n"',
        '"POT-Creation-Date: 2026-11-25T00:00:00+00:00\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Language: nl_NL\\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
        '',
    ]

    for (context, text) in sorted(singles.keys(), key=lambda item: (item[1].lower(), item[0])):
        if context:
            lines.append(f'msgctxt "{po_escape(context)}"')

        lines.append(f'msgid "{po_escape(text)}"')
        lines.append('msgstr ""')
        lines.append('')

    for (singular, plural) in sorted(plurals.keys()):
        lines.append(f'msgid "{po_escape(singular)}"')
        lines.append(f'msgid_plural "{po_escape(plural)}"')
        lines.append('msgstr[0] ""')
        lines.append('msgstr[1] ""')
        lines.append('')

    OUT.parent.mkdir(exist_ok=True)
    OUT.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {OUT} ({len(singles)} strings, {len(plurals)} plural pairs)")


if __name__ == "__main__":
    main()
