#!/usr/bin/env python3
"""Bake shared/i18n/*.json into shared/bible-brain-players.js (between the BBP_LOCALES markers).

Run after editing a translation:  python scripts/build_locales.py
Use --check in CI to confirm the widget is up to date."""
import glob, json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
JS = os.path.join(ROOT, "shared", "bible-brain-players.js")
REQUIRED = "country language version recording book chapter loading error noversions noconfig prev next smaller larger speed autoplay autoclip listen clips nowplaying reflect share copylink copied whatsapp facebook telegram poweredby chapterlabel play pause back forward seek".split()


def build():
    locales = {}
    for path in sorted(glob.glob(os.path.join(ROOT, "shared", "i18n", "*.json"))):
        with open(path, encoding="utf-8") as f:
            d = json.load(f)
        missing = [k for k in REQUIRED if not d["labels"].get(k)]
        if missing:
            raise SystemExit("%s is missing labels: %s" % (os.path.basename(path), ", ".join(missing)))
        if len(d["prompts"]) < 1:
            raise SystemExit("%s needs at least one reflection prompt" % os.path.basename(path))
        locales[d["code"]] = {"rtl": bool(d.get("rtl")), "labels": {k: d["labels"][k] for k in REQUIRED}, "prompts": d["prompts"]}
    body = json.dumps(locales, ensure_ascii=False, separators=(",", ":"))
    return "\t/*BBP_LOCALES_START*/\n\tvar LOCALES = " + body + ";\n\t/*BBP_LOCALES_END*/"


def main():
    with open(JS, encoding="utf-8") as f:
        src = f.read()
    new = re.sub(r"\t/\*BBP_LOCALES_START\*/.*?/\*BBP_LOCALES_END\*/", lambda m: build(), src, flags=re.S)
    if "--check" in sys.argv:
        if new != src:
            print("shared/bible-brain-players.js is out of date. Run: python scripts/build_locales.py")
            sys.exit(1)
        print("locales up to date")
        return
    with open(JS, "w", encoding="utf-8") as f:
        f.write(new)
    print("locales written:", ", ".join(sorted(json.loads(re.search(r"var LOCALES = (.*?);\n", new, re.S).group(1)).keys())))


if __name__ == "__main__":
    main()
