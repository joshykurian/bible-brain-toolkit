#!/usr/bin/env python3
"""Fail if the version number differs between VERSION and each package."""
import os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def read(p):
    with open(os.path.join(ROOT, p), encoding="utf-8") as f:
        return f.read()


want = read("VERSION").strip()
checks = {
    "WordPress plugin header": (read("packages/wordpress/bible-brain-players/bible-brain-players.php"), r"\* Version:\s+(\S+)"),
    "WordPress BBPLAY_VERSION": (read("packages/wordpress/bible-brain-players/bible-brain-players.php"), r"define\( 'BBPLAY_VERSION', '([^']+)'"),
    "WordPress readme Stable tag": (read("packages/wordpress/bible-brain-players/readme.txt"), r"Stable tag:\s*(\S+)"),
    "Joomla manifest": (read("packages/joomla/plg_system_biblebrain/biblebrain.xml"), r"<version>([^<]+)</version>"),
    "Drupal library": (read("packages/drupal/bible_brain/bible_brain.libraries.yml"), r"version:\s*(\S+)"),
    "Downloader": (read("packages/downloader/main.py"), r'VERSION = "([^"]+)"'),
}
bad = 0
for name, (text, pat) in checks.items():
    m = re.search(pat, text)
    got = m.group(1) if m else "(not found)"
    ok = got == want
    bad += not ok
    print("%-30s %-10s %s" % (name, got, "ok" if ok else "MISMATCH (expected %s)" % want))
sys.exit(1 if bad else 0)
