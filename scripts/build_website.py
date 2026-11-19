#!/usr/bin/env python3
"""Build the upload-ready website folder for https://www.shalomdesigns2dio.com/bible-brain-toolkit/

  python scripts/build_website.py

Runs build_site.py first, then writes dist/website/bible-brain-toolkit/ (the folder to upload) and
dist/bible-brain-toolkit-website.zip. All links inside the site are relative, so the folder works under any path.
Adds an .htaccess (media types, caching, safe defaults for Apache), robots.txt, sitemap.xml and UPLOAD.txt."""
import os, shutil, subprocess, sys, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = "https://www.shalomdesigns2dio.com/bible-brain-toolkit/"
OUT = os.path.join(ROOT, "dist", "website", "bible-brain-toolkit")

HTACCESS = """# Bible Brain Toolkit demo site - safe defaults for Apache (cPanel, most shared hosting).
# Delete this file if your host does not use Apache; the site works without it.
Options -Indexes
DirectoryIndex index.html

<IfModule mod_mime.c>
  AddType audio/mpeg .mp3
  AddType video/mp4 .mp4
  AddType video/webm .webm
  AddType application/json .json
  AddType text/css .css
  AddType application/javascript .js
</IfModule>

<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set Referrer-Policy "strict-origin-when-cross-origin"
  <FilesMatch "\\.(mp3|mp4|webm|css|js|json)$">
    Header set Cache-Control "public, max-age=86400"
  </FilesMatch>
  <FilesMatch "\\.html$">
    Header set Cache-Control "public, max-age=300"
  </FilesMatch>
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
"""

UPLOAD = """Bible Brain Toolkit - website upload
====================================
Target address: %s

1. Upload the whole folder "bible-brain-toolkit" so that it sits next to your other site pages,
   for example public_html/bible-brain-toolkit/ (or the matching folder of your CMS or host).
   Or upload the ZIP and extract it in public_html/.
2. Open %s - the players should load with sample data.
   Open %sgenerator.html - the embed code generator.
3. Audio and video need no extra setup. Seeking in audio needs "Range" requests, which Apache and nginx allow by default.
4. If you use a WordPress site for the main website, upload to the server folder, not through the Media Library.
   A folder with its own index.html is served before WordPress, on most hosts.
5. Updating: build again (python scripts/build_website.py), then replace the folder.

Full guide: docs/website-hosting.md in the repository.
""" % (BASE, BASE, BASE)


def main():
    subprocess.check_call([sys.executable, os.path.join(ROOT, "scripts", "build_site.py")])
    if os.path.isdir(os.path.join(ROOT, "dist", "website")):
        shutil.rmtree(os.path.join(ROOT, "dist", "website"))
    shutil.copytree(os.path.join(ROOT, "site"), OUT)
    w = lambda n, t: open(os.path.join(OUT, n), "w", encoding="utf-8", newline="\n").write(t)
    w(".htaccess", HTACCESS)
    w("robots.txt", "User-agent: *\nAllow: /\nSitemap: %ssitemap.xml\n" % BASE)
    w("sitemap.xml", '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
      '  <url><loc>%s</loc></url>\n  <url><loc>%sgenerator.html</loc></url>\n</urlset>\n' % (BASE, BASE))
    w("UPLOAD.txt", UPLOAD)
    zpath = os.path.join(ROOT, "dist", "bible-brain-toolkit-website.zip")
    with zipfile.ZipFile(zpath, "w", zipfile.ZIP_DEFLATED) as z:
        for base, _d, files in os.walk(OUT):
            for fn in sorted(files):
                full = os.path.join(base, fn)
                z.write(full, os.path.join("bible-brain-toolkit", os.path.relpath(full, OUT)).replace(os.sep, "/"))
    print("website folder:", os.path.relpath(OUT, ROOT))
    print("website zip:   ", os.path.relpath(zpath, ROOT))


if __name__ == "__main__":
    main()
