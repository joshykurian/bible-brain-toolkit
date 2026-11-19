#!/usr/bin/env python3
"""Build installable zip files into dist/.

    python scripts/build.py

Produces (VERSION is read from the VERSION file):
  bible-brain-downloader-<v>.zip       desktop downloader
  bible-brain-html5-embed-<v>.zip      HTML5 players + proxy + generator
  bible-brain-wordpress-<v>.zip        WordPress plugin (folder: bible-brain-players)
  bible-brain-drupal-<v>.zip           Drupal module (folder: bible_brain)
  plg_system_biblebrain-joomla-<v>.zip Joomla plugin (manifest at zip root, as Joomla requires)
"""
import os, subprocess, sys, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DIST = os.path.join(ROOT, "dist")
SKIP_DIRS = {"__pycache__", ".git", "node_modules"}
SKIP_FILES = {".DS_Store", "config.php"}


def version():
    with open(os.path.join(ROOT, "VERSION"), encoding="utf-8") as f:
        return f.read().strip()


def add_tree(z, src, arc_root):
    for base, dirs, files in os.walk(src):
        dirs[:] = sorted(d for d in dirs if d not in SKIP_DIRS)
        rel_base = os.path.relpath(base, src)
        # keep the html5 cache folder but never ship cached data
        in_cache = os.path.basename(base) == "cache"
        for f in sorted(files):
            if f in SKIP_FILES or f.endswith(".pyc") or (in_cache and f.startswith("bbc_")):
                continue
            rel = os.path.normpath(os.path.join(rel_base, f))
            arc = os.path.join(arc_root, rel) if arc_root else rel
            z.write(os.path.join(base, f), arc.replace(os.sep, "/"))


def main():
    for script in ("sync_shared.py", "build_locales.py", "check_versions.py"):
        args = [sys.executable, os.path.join(ROOT, "scripts", script)]
        if script != "check_versions.py":
            args.append("--check")
        subprocess.check_call(args)
    v = version()
    os.makedirs(DIST, exist_ok=True)
    jobs = [
        ("bible-brain-downloader-%s.zip" % v, "packages/downloader", "bible-brain-downloader"),
        ("bible-brain-html5-embed-%s.zip" % v, "packages/html5-embed", "bible-brain-html5-embed"),
        ("bible-brain-wordpress-%s.zip" % v, "packages/wordpress/bible-brain-players", "bible-brain-players"),
        ("bible-brain-drupal-%s.zip" % v, "packages/drupal/bible_brain", "bible_brain"),
        ("plg_system_biblebrain-joomla-%s.zip" % v, "packages/joomla/plg_system_biblebrain", ""),
    ]
    for name, src, root in jobs:
        out = os.path.join(DIST, name)
        with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
            add_tree(z, os.path.join(ROOT, src), root)
        print("built %-44s %5d KB" % (name, os.path.getsize(out) // 1024))


if __name__ == "__main__":
    main()
