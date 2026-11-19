"""Tests for the GitHub Pages demo site (site/): served statically, exactly as Pages would serve it.
Run: python -m unittest discover -s tests/js -p "test_site.py" -v"""
import functools, http.server, os, re, shutil, sys, threading, unittest

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, "tests"))
import helpers  # noqa: E402

try:
    from playwright.sync_api import sync_playwright
except ImportError:  # pragma: no cover
    sync_playwright = None

SITE = os.path.join(ROOT, "site")


class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a):
        pass


@unittest.skipUnless(sync_playwright and shutil.which("php"), "playwright and php are required")
class SiteTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.web = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Quiet, directory=SITE))
        threading.Thread(target=cls.web.serve_forever, daemon=True).start()
        cls.url = "http://127.0.0.1:%d/" % cls.web.server_address[1]
        cls.mock, cls.base = helpers.start_mock()
        cls.px = helpers.PhpProxy(cls.base)
        cls.pw = sync_playwright().start()
        cls.browser = cls.pw.chromium.launch(args=["--disable-features=LocalNetworkAccessChecks"])

    @classmethod
    def tearDownClass(cls):
        cls.browser.close(); cls.pw.stop(); cls.px.stop(); cls.mock.shutdown(); cls.web.shutdown()

    def page(self, path="", width=1280):
        ctx = self.browser.new_context(viewport={"width": width, "height": 900})
        self.addCleanup(ctx.close)
        p = ctx.new_page()
        self.errors = []
        p.on("pageerror", lambda e: self.errors.append(str(e)))
        p.on("console", lambda m: self.errors.append(m.text) if m.type == "error" else None)
        p.goto(self.url + path)
        return p

    def ready(self, p):
        p.wait_for_function("document.querySelector('#hero-player').innerText.length > 60 && document.querySelector('#reader-player').innerText.length > 60 "
                            "&& document.querySelector('#video-player').innerText.length > 60")

    def test_players_load_with_sample_data_and_no_errors(self):
        p = self.page(); self.ready(p)
        self.assertIn("John 3", p.inner_text("#hero-player"))
        self.assertIn("Nicodemus", p.inner_text("#reader-player"))
        self.assertEqual(p.locator("#video-player .bbp-clip").count(), 2)
        self.assertIn("not Bible Brain content", p.inner_text("body"))
        self.assertEqual(self.errors, [])

    def test_every_language_word_switches_the_players(self):
        expected = {"hi": "देश", "bn": "দেশ", "ne": "देश", "si": "රට", "ml": "രാജ്യം", "ur": "ملک", "en": "Country"}
        p = self.page(); self.ready(p)
        for code, word in expected.items():
            with self.subTest(lang=code):
                p.click("#words button[lang=%s]" % code)
                p.wait_for_function("document.querySelector('#hero-player select') && document.querySelector('#hero-player select').getAttribute('aria-label') === %r" % word)
                self.assertEqual(p.get_attribute("#words button[lang=%s]" % code, "aria-pressed"), "true")
                self.assertEqual(p.locator("#words button[aria-pressed=true]").count(), 1)
        self.assertEqual(self.errors, [])

    def test_urdu_is_right_to_left_everywhere(self):
        p = self.page(); self.ready(p)
        p.click("#words button[lang=ur]")
        p.wait_for_function("document.querySelector('#reader-player .bbp-ready') && document.querySelector('#reader-player .bbp-ready').getAttribute('dir') === 'rtl'")
        for host in ("hero-player", "reader-player", "video-player"):
            self.assertEqual(p.get_attribute("#%s .bbp" % host, "dir"), "rtl", host)

    def test_audio_plays_and_the_reader_highlights_verses(self):
        p = self.page(); self.ready(p)
        p.click("#reader-player .bbp-play")
        p.wait_for_selector("#reader-player .bbp-play[data-state=pause]", timeout=8000)
        p.wait_for_selector("#reader-player .bbp-v.is-now", timeout=8000)
        p.click("#hero-player .bbp-play")
        p.wait_for_selector("#hero-player .bbp-play[data-state=pause]", timeout=8000)

    def test_demo_audio_is_audible_speech_and_verses_follow_the_voice(self):
        """The audio must really play (clock advances, not muted) and the highlighted verse must follow the recorded timings."""
        ctx = self.browser.new_context(viewport={"width": 1280, "height": 900})
        self.addCleanup(ctx.close)
        ctx.add_init_script("window.__audio=[];var A=window.Audio;window.Audio=function(s){var e=new A(s);window.__audio.push(e);return e};")
        p = ctx.new_page(); p.goto(self.url); self.ready(p)
        p.click("#reader-player .bbp-play")
        p.wait_for_function("window.__audio.some(function(a){return !a.paused && a.currentTime > 0.8 && !a.muted && a.volume > 0 && a.src.indexOf('demo-john-3.mp3') > -1})", timeout=15000)
        # no seeking here: this plain test server has no HTTP range support, which browsers need to seek (GitHub Pages has it)
        p.wait_for_selector("#reader-player .bbp-v.is-now[data-v='1']", timeout=8000)
        p.wait_for_selector("#reader-player .bbp-v.is-now[data-v='2']", timeout=15000)  # verse 2 starts about 5 seconds into the voice
        self.assertIn("Nicodemus", p.inner_text("#reader-player"))

    def test_video_clip_switching(self):
        p = self.page(); self.ready(p)
        src1 = p.eval_on_selector("#video-player video", "e=>e.currentSrc || e.src")
        self.assertRegex(src1, r"demo-clip-1\.(mp4|webm)")
        p.click("#video-player .bbp-clip:has-text('Sample clip 2')")
        p.wait_for_function("(document.querySelector('#video-player video').currentSrc || document.querySelector('#video-player video').src).indexOf('demo-clip-2') > -1")

    def test_install_tabs(self):
        p = self.page()
        self.assertTrue(p.is_visible("#panel-wp"))
        p.click("#tab-joomla")
        self.assertTrue(p.is_visible("#panel-joomla")); self.assertTrue(p.is_hidden("#panel-wp"))
        self.assertIn("{bbp audio", p.inner_text("#panel-joomla .code"))
        p.focus("#tab-joomla"); p.keyboard.press("ArrowRight")
        self.assertTrue(p.is_visible("#panel-drupal"))

    def test_own_proxy_requires_https_except_localhost(self):
        p = self.page(); self.ready(p)
        p.fill("#own-url", "http://insecure.example/bbp-proxy.php?ep=")
        p.click("#own-form button[type=submit]")
        self.assertIn("https://", p.inner_text("#own-status"))
        self.assertIn("Nicodemus", p.inner_text("#reader-player"))   # still the demo

    def test_own_proxy_shows_server_content_then_resets(self):
        p = self.page(); self.ready(p)
        self.assertNotIn("Hindi OV", p.inner_html("#hero-player"))
        p.fill("#own-url", self.px.url + "/bbp-proxy.php?ep=")      # the real PHP proxy talking to the mock Bible Brain
        p.click("#own-form button[type=submit]")
        p.wait_for_function("document.querySelector('#hero-player').innerHTML.indexOf('Hindi OV') > -1", timeout=10000)
        self.assertEqual(p.locator("#hero-player select[aria-label=Country] option").count(), 3)   # the mock has 3 countries, the demo has 7
        p.wait_for_function("document.querySelector('#reader-player').innerText.indexOf('Nicodemus') > -1", timeout=10000)
        p.click("#own-reset")
        p.wait_for_function("document.querySelector('#hero-player select[aria-label=Country]') && document.querySelectorAll('#hero-player select[aria-label=Country] option').length === 7", timeout=10000)
        self.assertEqual(self.errors, [])

    def test_generator_runs_on_demo_data(self):
        p = self.page("generator.html")
        p.wait_for_function("document.querySelector('#country') && document.querySelector('#country').options.length > 3")
        self.assertIn("Demo.", p.inner_text("body"))
        self.assertIn("India", p.inner_text("#country"))
        hrefs = p.eval_on_selector_all("#about a", "e=>e.map(a=>a.href)")
        self.assertIn("https://4.dbt.io/api_key/request", hrefs)

    def test_no_horizontal_scroll_on_phone(self):
        p = self.page(width=390); self.ready(p)
        self.assertLessEqual(p.evaluate("document.documentElement.scrollWidth"), 390)

    def test_local_links_and_assets_exist(self):
        html = open(os.path.join(SITE, "index.html"), encoding="utf-8").read()
        for ref in re.findall(r'(?:href|src)="([^"#:]+)"', html):
            self.assertTrue(os.path.exists(os.path.join(SITE, ref)), ref)
        for f in ("demo-john-3.mp3", "demo-psalm-23.mp3", "demo-clip-1.mp4", "demo-clip-1.webm", "demo-clip-2.mp4", "demo-clip-2.webm"):
            self.assertTrue(os.path.getsize(os.path.join(SITE, "media", f)) > 10000, f)
        self.assertTrue(os.path.exists(os.path.join(SITE, "media", "demo-timings.json")))

    def test_demo_media_is_small_enough_for_phones(self):
        total = sum(os.path.getsize(os.path.join(SITE, "media", f)) for f in os.listdir(os.path.join(SITE, "media")))
        self.assertLess(total, 1_500_000)


if __name__ == "__main__":
    unittest.main()
