#!/usr/bin/env python3
"""Copy the shared player engine (shared/) into every package.

shared/ is the single source of truth. Never edit the copies inside packages/.
    python scripts/sync_shared.py          copy files
    python scripts/sync_shared.py --check  fail if any copy differs (used in CI)"""
import filecmp, os, shutil, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = "packages/"
JS, CSS, GEN, CORE = "shared/bible-brain-players.js", "shared/bible-brain-players.css", "shared/generator.html", "shared/php/BibleBrainCore.php"

TARGETS = {
    JS: [P + "html5-embed/bible-brain-players.js", P + "wordpress/bible-brain-players/assets/js/frontend.js",
         P + "drupal/bible_brain/js/bible-brain-players.js", P + "drupal/bible_brain/generator/bible-brain-players.js",
         P + "joomla/plg_system_biblebrain/media/js/bible-brain-players.js", P + "joomla/plg_system_biblebrain/media/generator/bible-brain-players.js"],
    CSS: [P + "html5-embed/bible-brain-players.css", P + "wordpress/bible-brain-players/assets/css/frontend.css",
          P + "drupal/bible_brain/css/bible-brain-players.css", P + "drupal/bible_brain/generator/bible-brain-players.css",
          P + "joomla/plg_system_biblebrain/media/css/bible-brain-players.css", P + "joomla/plg_system_biblebrain/media/generator/bible-brain-players.css"],
    GEN: [P + "html5-embed/generator.html", P + "drupal/bible_brain/generator/generator.html", P + "joomla/plg_system_biblebrain/media/generator/generator.html"],
    CORE: [P + "html5-embed/BibleBrainCore.php", P + "drupal/bible_brain/lib/BibleBrainCore.php", P + "joomla/plg_system_biblebrain/lib/BibleBrainCore.php"],
}


def main():
    check = "--check" in sys.argv
    stale = []
    for src, dests in TARGETS.items():
        s = os.path.join(ROOT, src)
        for d in dests:
            dp = os.path.join(ROOT, d)
            same = os.path.isfile(dp) and filecmp.cmp(s, dp, shallow=False)
            if same:
                continue
            if check:
                stale.append(d)
            else:
                os.makedirs(os.path.dirname(dp), exist_ok=True)
                shutil.copyfile(s, dp)
                print("updated", d)
    if check:
        if stale:
            print("These files differ from shared/ (run: python scripts/sync_shared.py):")
            for d in stale:
                print("  ", d)
            sys.exit(1)
        print("all package copies match shared/")


if __name__ == "__main__":
    main()
