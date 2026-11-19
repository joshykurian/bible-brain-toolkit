"""Regenerate the README screenshots:  python tests/js/screenshots.py
They are taken against a local MOCK of the Bible Brain API with public-domain (KJV) sample text, not live data."""
import os, sys
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, "tests"))
sys.path.insert(0, os.path.join(ROOT, "tests", "js"))
import base64, io  # noqa: E402
import helpers  # noqa: E402
from test_widgets import PAGE, READER, AUDIO, VIDEO  # noqa: E402
from playwright.sync_api import sync_playwright  # noqa: E402

OUT = os.path.join(ROOT, "docs", "images")
SHOTS = [  # file, body, lang, width, scale
    ("reader.png", READER, "en", 760, 2),
    ("audio-player.png", AUDIO, "en", 760, 2),
    ("video-player.png", VIDEO, "en", 760, 2),
    ("reader-hindi-ui.png", READER, "hi", 760, 2),
    ("audio-urdu-rtl.png", AUDIO, "ur", 760, 2),
    ("phone-audio.png", AUDIO, "en", 390, 2),
]


def poster_data_uri():
    """A plain labelled frame so the video screenshot is not a spinner (the mock serves placeholder bytes, not real video)."""
    from PIL import Image, ImageDraw
    im = Image.new("RGB", (1280, 720), (38, 32, 34))
    d = ImageDraw.Draw(im)
    d.polygon([(590, 300), (590, 420), (700, 360)], fill=(245, 240, 241))
    d.text((40, 660), "Demo frame - the real player streams HLS video from Bible Brain", fill=(190, 180, 183))
    buf = io.BytesIO()
    im.save(buf, "PNG")
    return "data:image/png;base64," + base64.b64encode(buf.getvalue()).decode()


def make_handler(html):
    return lambda route: route.fulfill(body=html, content_type="text/html; charset=utf-8")


def main():
    os.makedirs(OUT, exist_ok=True)
    srv, base = helpers.start_mock()
    px = helpers.PhpProxy(base)
    try:
        with sync_playwright() as pw:
            br = pw.chromium.launch(args=["--disable-features=LocalNetworkAccessChecks"])
            for name, body, lang, width, scale in SHOTS:
                ctx = br.new_context(viewport={"width": width + 40, "height": 1200}, device_scale_factor=scale)
                page = ctx.new_page()
                html = PAGE.format(lang=lang, body=body).replace("<body>", '<body style="margin:20px;background:#fff">')
                page.route(px.url + "/t.html", make_handler(html))
                page.goto(px.url + "/t.html")
                page.wait_for_selector("#w.bbp-ready")
                page.wait_for_function("document.querySelector('#w').innerText.length > 80")
                page.wait_for_timeout(500)
                if "bbp-video" in body:
                    page.evaluate("""(uri) => { const v = document.querySelector('#w video'); v.pause(); v.removeAttribute('src'); v.poster = uri; v.load(); }""", poster_data_uri())
                    page.wait_for_timeout(300)
                page.locator("#w").screenshot(path=os.path.join(OUT, name))
                print("wrote", name)
                ctx.close()
            br.close()
    finally:
        px.stop()
        srv.shutdown()


if __name__ == "__main__":
    main()
