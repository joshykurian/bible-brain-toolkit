# Hosting the demo website

The Bible Brain Toolkit demo and embed code generator are hosted by Shalom Design S2dio (SDS) at
<https://www.shalomdesigns2dio.com/bible-brain-toolkit/>. The source code is at
<https://github.com/shalomdesigns2dio/bible-brain-toolkit>.

The site is static: plain HTML, CSS and JavaScript plus a few small sample audio and video files. It needs no database, no PHP and no
API key. All links are relative, so the folder works under any path.

## Build the upload package

```
python scripts/build_website.py
```

This writes:

- `dist/website/bible-brain-toolkit/` - the folder to upload
- `dist/bible-brain-toolkit-website.zip` - the same folder as one ZIP

The folder contains the landing page (`index.html`), the code generator (`generator.html`), `assets/`, `media/`, an `.htaccess`
for Apache, `robots.txt`, `sitemap.xml` and `UPLOAD.txt`.

## Upload

1. Upload the folder `bible-brain-toolkit` into the web root, for example `public_html/`, or upload the ZIP and extract it there
   (cPanel File Manager, FTP or SFTP).
2. Open <https://www.shalomdesigns2dio.com/bible-brain-toolkit/>. The players should load and play the sample chapter.
3. Open `generator.html` in the same folder to check the embed code generator.

If the main site runs on a CMS, a folder with its own `index.html` is normally served before the CMS. If your host sends the address
to the CMS instead, add an exception for `/bible-brain-toolkit/` in the host or CMS rewrite rules.

## Checks after uploading

- Audio plays and seeking works (the server must allow range requests; Apache and nginx do by default).
- Video plays (MP4 on Safari and Chrome, WebM as fallback). Both are included.
- The page and the generator show the same header, fonts and footer.
- The footer states that this is an unofficial community project, not published or supported by Faith Comes By Hearing.

## Updating

Pull the latest repository, run `python scripts/build_website.py` again and replace the uploaded folder. Pushing to `main` also
keeps the optional GitHub Pages mirror up to date (`.github/workflows/pages.yml`).

## Content notice

The sample text is the public-domain King James Version read by a computer voice, and the video is generated test footage. No
Bible Brain recordings are included. Visitors who use their own proxy see their own Bible Brain content, subject to the license
of each Bible version.
