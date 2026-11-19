#!/usr/bin/env python3
"""Build the GitHub Pages demo site in site/ from the shared sources.  python scripts/build_site.py [--check]

Copies the shared player JS/CSS into site/assets and derives site/generator.html (the embed code generator wired to the demo
API). The rest of site/ (index.html, assets/site.js, assets/demo-api.js, media/) is hand-written and committed."""
import os, shutil, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(ROOT, "site")
COPIES = [("shared/bible-brain-players.js", "site/assets/bible-brain-players.js"),
          ("shared/bible-brain-players.css", "site/assets/bible-brain-players.css"),
          # also at the site root, because the code generator writes snippets that point to these file names next to the page
          ("shared/bible-brain-players.js", "site/bible-brain-players.js"),
          ("shared/bible-brain-players.css", "site/bible-brain-players.css")]

BANNER = ('<p class="demo-note"><strong>Demo.</strong> This page runs on sample data, so the proxy address below is a placeholder. '
          'In your own copy it is filled in for you. <a href="./">Back to the demo</a></p>')

NAV = ('<a href="./#try">Read and watch</a>\n      <a href="./#install">Add to your site</a>\n      <a href="./#own">Use your content</a>\n'
       '      <a href="./#about">About</a>\n      <a href="generator.html" aria-current="page">Code generator</a>\n'
       '      <a href="https://github.com/shalomdesigns2dio/bible-brain-toolkit">GitHub</a>')


def generator():
    s = open(os.path.join(ROOT, "shared", "generator.html"), encoding="utf-8").read()
    for needle in ('value="bbp-proxy.php?ep="', "<h1>Embed code generator</h1>", "</head>", "<!--site-nav-->",
                   '<a class="brand" href="https://www.shalomdesigns2dio.com/bible-brain-toolkit/">'):
        if s.count(needle) != 1:
            sys.exit("generator.html changed: cannot find %r exactly once" % needle)
    s = s.replace('value="bbp-proxy.php?ep="', 'value="demo-api/"')
    s = s.replace("<h1>Embed code generator</h1>", "<h1>Embed code generator</h1>\n  " + BANNER, 1)
    s = s.replace("<!--site-nav-->", NAV, 1)
    s = s.replace('<a class="brand" href="https://www.shalomdesigns2dio.com/bible-brain-toolkit/">', '<a class="brand" href="./">', 1)
    s = s.replace("</head>", '<style>.demo-note { background: #FFF3CD; border: 1px solid #E0C46C; border-radius: 8px; padding: .6rem .9rem; margin: 0 0 1.25rem; max-width: 40rem; }</style>\n<script src="assets/demo-api.js"></script>\n</head>', 1)
    return s.encode("utf-8")


def iframe():
    s = open(os.path.join(ROOT, "packages", "html5-embed", "iframe.html"), encoding="utf-8").read()
    if s.count("</head>") != 1:
        sys.exit("iframe.html changed: cannot find </head> exactly once")
    return s.replace("</head>", '<script src="assets/demo-api.js"></script>\n</head>', 1).encode("utf-8")


MEDIA = ["site/media/demo-john-3.mp3", "site/media/demo-psalm-23.mp3", "site/media/demo-timings.json", "site/media/demo-clip-1.mp4", "site/media/demo-clip-1.webm",
         "site/media/demo-clip-2.mp4", "site/media/demo-clip-2.webm"]


def main():
    check = "--check" in sys.argv
    bad = []
    missing = [m for m in MEDIA if not os.path.isfile(os.path.join(ROOT, m))]
    if missing:
        print("demo media is missing (audio and video would not play): " + ", ".join(missing))
        print("Restore the files, or regenerate them with: python scripts/build_demo_media.py")
        print("If they exist on your disk but not on GitHub, an ignore rule is hiding them: see .gitignore")
        sys.exit(1)
    wanted = [(dst, open(os.path.join(ROOT, src), "rb").read()) for src, dst in COPIES] + [("site/generator.html", generator()), ("site/iframe.html", iframe())]
    for dst, data in wanted:
        path = os.path.join(ROOT, dst)
        same = os.path.exists(path) and open(path, "rb").read() == data
        if check:
            if not same:
                bad.append(dst)
        elif not same:
            os.makedirs(os.path.dirname(path), exist_ok=True)
            open(path, "wb").write(data)
            print("updated", dst)
    if check:
        if bad:
            print("site is out of date, run: python scripts/build_site.py\n  " + "\n  ".join(bad))
            sys.exit(1)
        print("site copies match shared/")


if __name__ == "__main__":
    main()
