#!/usr/bin/env python3
"""Build a WordPress.org-ready copy of the plugin, plus the listing artwork.

    python scripts/make_wporg_package.py --variant independent --wp-user YOURNAME --tested-up-to 6.9 --hls path/to/hls.min.js
    python scripts/make_wporg_package.py --variant official    --wp-user FCBHUSER --tested-up-to 6.9 --hls path/to/hls.min.js

What it does, and why (see docs/submission/wordpress-org.md):
  * variant "independent": a name and slug that follow the WordPress.org trademark rule
    ("<Feature> for <Brand>", never the brand first), for anyone who is not Faith Comes By Hearing.
  * variant "official": keeps the name "Bible Brain Players". Submit it only from an official
    Faith Comes By Hearing account (the trademark owner).
  * Puts the readme into directory form (valid Contributors, Tested up to).
  * Bundles hls.js locally and removes the CDN fallback (reviewers require local scripts).
  * Writes the listing artwork (icon, banner, screenshots) into dist/wporg/<slug>-assets/.

Output: dist/wporg/<slug>/ (the plugin folder), dist/wporg/<slug>-<version>.zip, dist/wporg/<slug>-assets/
"""
import argparse, os, re, shutil, sys, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "packages", "wordpress", "bible-brain-players")
OLD_SLUG = "bible-brain-players"

VARIANTS = {
    "independent": {
        "name": "Audio Bible Players for Bible Brain",
        "slug": "audio-bible-players-for-bible-brain",
        "short": "Audio-first Bible Reader, Audio Player and Video Player. Visitors choose country, language and version. Uses the free Bible Brain API.",
    },
    "official": {
        "name": "Bible Brain Players",
        "slug": "bible-brain-players",
        "short": "Audio-first Bible Reader, Audio Player and Video Player. Visitors choose country, language and version. Uses the free Bible Brain API.",
    },
}


def fail(msg):
    print("error: " + msg, file=sys.stderr)
    sys.exit(1)


def read(p):
    with open(p, encoding="utf-8") as f:
        return f.read()


def write(p, s):
    with open(p, "w", encoding="utf-8", newline="\n") as f:
        f.write(s)


def replace_once(text, old, new, what):
    if text.count(old) < 1:
        fail("expected text not found while preparing the package (%s). The plugin code changed; update this script." % what)
    return text.replace(old, new)


def prepare(args):
    v = VARIANTS[args.variant]
    slug, name = v["slug"], v["name"]
    out_root = os.path.join(ROOT, "dist", "wporg")
    target = os.path.join(out_root, slug)
    if os.path.isdir(target):
        shutil.rmtree(target)
    os.makedirs(out_root, exist_ok=True)
    shutil.copytree(SRC, target, ignore=shutil.ignore_patterns("__pycache__", "*.pyc", ".DS_Store"))

    # 1. rename the main file and swap the slug / text domain / page slug / REST namespace
    os.rename(os.path.join(target, OLD_SLUG + ".php"), os.path.join(target, slug + ".php"))
    for base, _dirs, files in os.walk(target):
        for fn in files:
            if not fn.endswith((".php", ".txt", ".js")):
                continue
            p = os.path.join(base, fn)
            s = read(p)
            n = s.replace(OLD_SLUG, slug) if slug != OLD_SLUG else s
            if n != s:
                write(p, n)

    # 2. plugin header: name and author
    main = os.path.join(target, slug + ".php")
    s = read(main)
    s = replace_once(s, " * Plugin Name:       Bible Brain Players", " * Plugin Name:       " + name, "plugin name")
    if args.author:
        s = replace_once(s, " * Author:            Shalom Design S2dio (SDS)", " * Author:            " + args.author, "author")
    if args.variant == "independent":
        s = s.replace(" * Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Bible Brain is a project of Faith Comes By Hearing. You need your own free API key.",
                      " * Not affiliated with or endorsed by Faith Comes By Hearing. Bible Brain is a service of Faith Comes By Hearing. You need your own free API key.")
    write(main, s)

    # 3. no CDN fallback: hls.js is bundled
    sh = os.path.join(target, "includes", "class-bbp-shortcodes.php")
    s = read(sh)
    s = replace_once(
        s,
        "		if ( BBPlay_Settings::get( 'cdn_hls' ) || file_exists( BBPLAY_DIR . 'assets/vendor/hls.min.js' ) ) {\n"
        "			$local = file_exists( BBPLAY_DIR . 'assets/vendor/hls.min.js' );\n"
        "			$src   = $local ? BBPLAY_URL . 'assets/vendor/hls.min.js' : 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js';\n"
        "			wp_register_script( 'bbplay-hls', $src, array(), '1.5.17', true );",
        "		if ( file_exists( BBPLAY_DIR . 'assets/vendor/hls.min.js' ) ) {\n"
        "			wp_register_script( 'bbplay-hls', BBPLAY_URL . 'assets/vendor/hls.min.js', array(), '1.5.17', true );",
        "hls registration",
    )
    write(sh, s)
    ad = os.path.join(target, "includes", "class-bbp-admin.php")
    s = read(ad)
    s, k = re.subn(r"\t+<label class=\"bbp-check\"><input type=\"checkbox\" name=\"<\?php echo esc_attr\( \$opt \); \?>\[cdn_hls\]\".*?</label>\n", "", s, flags=re.S)
    if k != 1:
        fail("could not remove the CDN checkbox from the settings page (found %d). Update this script." % k)
    write(ad, s)
    st = os.path.join(target, "includes", "class-bbp-settings.php")
    s = read(st)
    s = replace_once(s, "'cdn_hls'           => 1,", "'cdn_hls'           => 0,", "cdn default")
    write(st, s)

    # 4. bundle hls.js
    os.makedirs(os.path.join(target, "assets", "vendor"), exist_ok=True)
    if args.hls:
        if not os.path.isfile(args.hls):
            fail("--hls file not found: " + args.hls)
        shutil.copyfile(args.hls, os.path.join(target, "assets", "vendor", "hls.min.js"))
        write(os.path.join(target, "assets", "vendor", "README.txt"),
              "hls.min.js is hls.js 1.5.17 (Apache License 2.0), copied unchanged from the official release.\n"
              "Readable source code and releases: https://github.com/video-dev/hls.js\n")
    else:
        print("warning: no --hls given. Reviewers expect hls.js to be bundled; the package will not play video in Chrome/Firefox/Edge.")
        write(os.path.join(target, "assets", "vendor", "README.txt"), "Place hls.min.js here (see the readme).\n")

    # 5. readme.txt in directory form
    rd = os.path.join(target, "readme.txt")
    s = read(rd)
    s = replace_once(s, "=== Bible Brain Players ===", "=== " + name + " ===", "readme title")
    s = replace_once(s, "Contributors: bible-brain-toolkit-contributors", "Contributors: " + args.wp_user, "contributors")
    s = replace_once(s, "Requires PHP: 7.4\n", "Requires PHP: 7.4\nTested up to: " + args.tested_up_to + "\n", "tested up to")
    if args.variant == "independent":
        s = re.sub(r"\n== Notes ==.*\Z", "\n", s, flags=re.S)
    s = s.replace("Optional: hls.js from a CDN.", "hls.js is bundled.")
    s = re.sub(r"\*\*hls\.js is bundled\.\*\*.*?\n\n", "**hls.js is bundled.** Video streams play natively in Safari and on iOS. Other browsers use the hls.js library, which is included in this plugin (`assets/vendor/hls.min.js`) and served from your own site, so no other service is contacted.\n\n", s, flags=re.S)
    s = s.replace("Safari and iOS play Bible Brain video natively. Chrome, Firefox and Edge need the hls.js library, which is included in the release from WordPress.org. If you installed from another source, see the \"External services\" section.",
                  "Safari and iOS play Bible Brain video natively. Chrome, Firefox and Edge use the hls.js library that is included in the plugin. If a stream still does not start, check that the Bible version has video and try another version.")
    if args.variant == "independent":
        s += ("\n== Notes ==\nThis plugin is not affiliated with or endorsed by Faith Comes By Hearing. Bible Brain is a service of Faith Comes By Hearing.\n")
    s = re.sub(r"\n{3,}", "\n\n", s)
    write(rd, s)

    # 6. the readme must stay within the directory limits
    first = re.search(r"\n\n(.+)\n\n== Description", s)
    if first and len(first.group(1)) > 150:
        fail("short description is %d characters; the limit is 150." % len(first.group(1)))
    tags = re.search(r"^Tags: (.*)$", s, flags=re.M).group(1).split(",")
    if len(tags) > 5:
        fail("more than 5 tags")

    # 7. nothing from the old identity may be left
    if slug != OLD_SLUG:
        for base, _d, files in os.walk(target):
            for fn in files:
                if fn.endswith((".php", ".txt")) and fn != "README.txt":
                    if OLD_SLUG in read(os.path.join(base, fn)):
                        fail("old slug still present in " + os.path.join(base, fn))
    return target, slug


def art(slug, out_dir, args):
    try:
        from PIL import Image, ImageDraw, ImageFont
    except ImportError:
        print("note: Pillow is not installed; skipping artwork.")
        return
    os.makedirs(out_dir, exist_ok=True)
    teal, ink, paper, maroon = (34, 94, 98), (24, 32, 34), (244, 247, 246), (122, 36, 48)

    def font(size, bold=True):
        for p in ("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",):
            if os.path.exists(p):
                return ImageFont.truetype(p, size)
        return ImageFont.load_default()

    def glyph(d, cx, cy, r, color):
        """Original mark: a play triangle inside a sound-wave arc."""
        for i, k in enumerate((1.0, 0.72)):
            rr = r * k
            d.arc([cx - rr, cy - rr, cx + rr, cy + rr], start=-50, end=50, fill=color, width=max(2, int(r * 0.12)))
        t = r * 0.42
        d.polygon([(cx - t * 0.7, cy - t), (cx - t * 0.7, cy + t), (cx + t * 0.9, cy)], fill=color)

    for size in (128, 256):
        im = Image.new("RGB", (size, size), teal)
        d = ImageDraw.Draw(im)
        glyph(d, size * 0.46, size * 0.5, size * 0.34, paper)
        im.save(os.path.join(out_dir, "icon-%dx%d.png" % (size, size)))
    for w, h in ((772, 250), (1544, 500)):
        im = Image.new("RGB", (w, h), teal)
        d = ImageDraw.Draw(im)
        s = w / 772.0
        d.rectangle([0, h - int(10 * s), w, h], fill=maroon)
        glyph(d, 120 * s, h / 2 - 4 * s, 70 * s, paper)
        d.text((230 * s, 78 * s), "Audio Bible, video and reader", font=font(int(26 * s)), fill=paper)
        d.text((230 * s, 126 * s), "for every language your visitors speak", font=font(int(19 * s), False), fill=(214, 232, 230))
        im.save(os.path.join(out_dir, "banner-%dx%d.png" % (w, h)))
    shots = [("audio-player.png", 1), ("reader.png", 2), ("video-player.png", 3)]
    for fn, n in shots:
        src = os.path.join(ROOT, "docs", "images", fn)
        if os.path.exists(src):
            shutil.copyfile(src, os.path.join(out_dir, "screenshot-%d.png" % n))
    write(os.path.join(out_dir, "README.txt"),
          "Upload these files to the plugin's /assets/ folder in WordPress.org SVN (not inside trunk).\n"
          "The icon and banner are original artwork. The screenshots come from the test data in this repository:\n"
          "replace them with screenshots of your own site before you submit.\n")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--variant", choices=sorted(VARIANTS), default="official")
    ap.add_argument("--wp-user", required=True, help="your WordPress.org username (goes in Contributors)")
    ap.add_argument("--tested-up-to", required=True, help="the newest WordPress version you actually tested, e.g. 6.9")
    ap.add_argument("--hls", help="path to hls.min.js 1.5.17 (from the official hls.js release)")
    ap.add_argument("--author", help="Author name for the plugin header")
    args = ap.parse_args()

    target, slug = prepare(args)
    with open(os.path.join(ROOT, "VERSION"), encoding="utf-8") as f:
        ver = f.read().strip()
    zpath = os.path.join(os.path.dirname(target), "%s-%s.zip" % (slug, ver))
    with zipfile.ZipFile(zpath, "w", zipfile.ZIP_DEFLATED) as z:
        for base, _d, files in os.walk(target):
            for fn in sorted(files):
                p = os.path.join(base, fn)
                z.write(p, os.path.join(slug, os.path.relpath(p, target)).replace(os.sep, "/"))
    art(slug, os.path.join(os.path.dirname(target), slug + "-assets"), args)
    print("plugin folder : " + os.path.relpath(target, ROOT))
    print("zip           : " + os.path.relpath(zpath, ROOT))
    print("listing assets: dist/wporg/%s-assets/" % slug)


if __name__ == "__main__":
    main()
