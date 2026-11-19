"""Browser smoke tests: real Chromium + real bbp-proxy.php (php -S) + mock Bible Brain API.
Run: python -m unittest discover -s tests/js -v    (needs playwright with Chromium, and the php CLI)"""
import json, os, shutil, sys, unittest

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, "tests"))
import helpers  # noqa: E402

try:
    from playwright.sync_api import sync_playwright
except ImportError:  # pragma: no cover
    sync_playwright = None

PAGE = """<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>widget test</title><link rel="stylesheet" href="/bible-brain-players.css"></head><body>{body}
<script src="/bible-brain-players.js" data-rest="/bbp-proxy.php?ep=" defer></script></body></html>"""

READER = '<div id="w" class="bbp-reader" data-country="IN" data-bible="HINDOV" data-book="JHN" data-chapter="3" data-listen="no"></div>'
AUDIO = '<div id="w" class="bbp-audio" data-country="IN" data-language="hin" data-bible="HINDOV"></div>'
VIDEO = '<div id="w" class="bbp-video" data-country="IN" data-language="hin"></div>'


@unittest.skipUnless(sync_playwright and shutil.which("php"), "playwright and php are required")
class WidgetTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.srv, cls.base = helpers.start_mock()
        cls.px = helpers.PhpProxy(cls.base)
        cls.pw = sync_playwright().start()
        # Everything runs on loopback in tests, which trips Chromium's local-network-access prompt for media
        # that lives on a different port. Real CDNs are not on loopback, so switch the check off here only.
        cls.browser = cls.pw.chromium.launch(args=["--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessForNavigations,BlockInsecurePrivateNetworkRequests"])

    @classmethod
    def tearDownClass(cls):
        cls.browser.close()
        cls.pw.stop()
        cls.px.stop()
        cls.srv.shutdown()

    def open(self, body, lang="en", wait="#w .bbp-foot, #w .bbp-status"):
        ctx = self.browser.new_context(viewport={"width": 420, "height": 900})
        self.addCleanup(ctx.close)
        page = ctx.new_page()
        self.errors, self.urls = [], []
        page.on("pageerror", lambda e: self.errors.append(str(e)))
        page.on("console", lambda m: self.errors.append(m.text) if m.type == "error" else None)
        page.on("request", lambda r: self.urls.append(r.url))
        # A site using GTM already has window.dataLayer; the widget only pushes to it when it exists.
        page.add_init_script("window.dataLayer = []; window.__bbp = []; document.addEventListener('bbp:event', function (e) { window.__bbp.push(e.detail); });")
        html = PAGE.format(lang=lang, body=body)
        page.route(self.px.url + "/t.html", lambda r: r.fulfill(body=html, content_type="text/html; charset=utf-8"))
        page.goto(self.px.url + "/t.html")
        page.wait_for_selector("#w.bbp-ready")
        page.wait_for_function("document.querySelector('#w').innerText.length > 40")
        page.wait_for_timeout(300)
        return page

    def assertClean(self):
        self.assertEqual(self.errors, [])
        self.assertFalse([u for u in self.urls if helpers.GOOD_KEY in u], "the API key must never appear in a browser request")

    # ---- reader -------------------------------------------------------------------
    def test_reader_shows_text_and_navigates(self):
        p = self.open(READER)
        self.assertIn("Nicodemus", p.inner_text("#w"))
        self.assertIn("John 3", p.inner_text("#w"))
        p.click("#w .bbp-chip:has-text('4')")
        p.wait_for_function("document.querySelector('#w').innerText.includes('Sample verse 1 of JHN 4')")
        self.assertIn("John 4", p.inner_text("#w"))
        self.assertClean()

    def test_reader_selector_cascade(self):
        p = self.open(READER)
        self.assertEqual(p.eval_on_selector_all("#w select", "e=>e.map(s=>s.getAttribute('aria-label'))")[:3], ["Country", "Language", "Version"])
        p.select_option("#w select[aria-label=Country]", "NP")
        p.wait_for_function("document.querySelector('#w').innerText.includes('Nepali')")
        self.assertIn("Nepali Version", p.inner_text("#w"))
        self.assertClean()

    def test_reader_without_selector_is_locked(self):
        p = self.open(READER.replace('data-listen="no"', 'data-listen="no" data-selector="no"'))
        self.assertTrue(p.is_hidden("#w .bbp-selector"))
        self.assertIn("Nicodemus", p.inner_text("#w"))
        self.assertClean()

    def test_font_size_buttons(self):
        p = self.open(READER)
        before = p.eval_on_selector("#w .bbp-text", "e=>parseFloat(getComputedStyle(e).fontSize)")
        p.click("#w [aria-label='Larger text']")
        after = p.eval_on_selector("#w .bbp-text", "e=>parseFloat(getComputedStyle(e).fontSize)")
        self.assertGreater(after, before)

    # ---- audio --------------------------------------------------------------------
    def test_audio_player_plays_and_changes_chapter(self):
        p = self.open(AUDIO)
        self.assertIn("John 1", p.inner_text("#w"))
        p.click("#w .bbp-play")
        p.wait_for_selector("#w .bbp-play[data-state=pause]", timeout=8000)
        p.click("#w .bbp-chip:has-text('3')")
        p.wait_for_function("document.querySelector('#w').innerText.includes('John 3')")
        self.assertClean()

    def test_audio_share_links(self):
        p = self.open(AUDIO)
        hrefs = p.eval_on_selector_all("#w .bbp-share a", "e=>e.map(a=>a.href)")
        self.assertTrue(any("wa.me" in h or "whatsapp" in h for h in hrefs), hrefs)
        self.assertTrue(any("t.me" in h or "telegram" in h for h in hrefs), hrefs)
        self.assertTrue(any("facebook" in h for h in hrefs), hrefs)

    def test_reflection_prompts_present(self):
        p = self.open(AUDIO)
        prompts = p.evaluate("document.querySelector('#w .bbp-prompts').textContent")
        self.assertIn("What did you hear", prompts)

    # ---- video --------------------------------------------------------------------
    def test_video_clip_list(self):
        p = self.open(VIDEO)
        txt = p.inner_text("#w")
        self.assertIn("Mark 1:1-8", txt)        # untitled clip falls back to a passage reference
        self.assertIn("Baptism of Jesus", txt)
        self.assertEqual(p.locator("#w .bbp-clip").count(), 2)   # the clip list follows the selected book (Mark)
        p.click("#w .bbp-clip:has-text('Baptism of Jesus')")
        p.wait_for_function("document.querySelector('#w .bbp-now, #w').innerText.includes('Baptism of Jesus')")
        self.assertClean()

    # ---- configuration modes ----------------------------------------------------------
    def test_data_bbp_json_mode(self):
        cfg = {"type": "reader", "uid": 1, "rest": "/bbp-proxy.php?ep=", "country": "IN", "language": "hin", "bible": "HINDOV", "book": "JHN",
               "chapter": 3, "selector": False, "share": True, "reflect": True, "listen": False, "prompts": [], "title": "CMS mode"}
        p = self.open("<div id='w' class='bbp' data-bbp='%s'></div>" % json.dumps(cfg))
        self.assertIn("Nicodemus", p.inner_text("#w"))
        self.assertClean()

    def test_dataLayer_events(self):
        p = self.open(AUDIO)
        p.click("#w .bbp-play")
        p.wait_for_function("window.dataLayer.some(e=>e.event==='bbp_play')", timeout=8000)
        events = p.evaluate("window.dataLayer.map(e=>e.event)")
        self.assertIn("bbp_play", events)
        self.assertGreaterEqual(p.evaluate("window.__bbp.length"), 1)   # the DOM event fires too

    # ---- localisation ---------------------------------------------------------------
    def test_all_bundled_locales_are_registered(self):
        p = self.open(READER)
        self.assertEqual(sorted(p.evaluate("window.BBPlayers.locales")), ["bn", "hi", "ml", "ne", "si", "ur"])

    def test_page_language_switches_labels(self):
        expected = {"hi": "देश", "bn": "দেশ", "ne": "देश", "si": "රට", "ml": "രാജ്യം", "ur": "ملک"}
        for code, word in expected.items():
            with self.subTest(lang=code):
                p = self.open(READER, lang=code)
                self.assertEqual(p.eval_on_selector("#w select", "e=>e.getAttribute('aria-label')"), word)

    def test_urdu_is_right_to_left(self):
        p = self.open(READER, lang="ur")
        self.assertEqual(p.get_attribute("#w", "dir"), "rtl")
        p2 = self.open(READER, lang="hi")
        self.assertIsNone(p2.get_attribute("#w", "dir"))

    def test_rtl_keeps_skip_labels_readable(self):
        p = self.open(AUDIO, lang="ur")
        self.assertEqual(p.eval_on_selector("#w .bbp-skip", "e=>getComputedStyle(e).direction"), "ltr")

    def test_selector_fields_share_the_row(self):
        p = self.open(READER)
        widths = p.eval_on_selector_all("#w .bbp-selector .bbp-field:not([hidden])", "e=>e.map(x=>Math.round(x.getBoundingClientRect().width))")
        self.assertEqual(len(widths), 3)
        self.assertGreaterEqual(min(widths), 150)

    def test_unknown_language_falls_back_to_english(self):
        p = self.open(READER, lang="fr")
        self.assertEqual(p.eval_on_selector("#w select", "e=>e.getAttribute('aria-label')"), "Country")

    def test_regional_language_tag_is_matched(self):
        p = self.open(READER, lang="hi-IN")
        self.assertEqual(p.eval_on_selector("#w select", "e=>e.getAttribute('aria-label')"), "देश")

    def test_data_lang_overrides_page_language(self):
        p = self.open(READER.replace("<div ", "<div data-lang='bn' "), lang="en")
        self.assertEqual(p.eval_on_selector("#w select", "e=>e.getAttribute('aria-label')"), "দেশ")

    # ---- generator About section ----------------------------------------------------------
    def test_generator_has_about_links(self):
        ctx = self.browser.new_context()
        self.addCleanup(ctx.close)
        page = ctx.new_page()
        page.goto(self.px.url + "/generator.html")
        hrefs = page.eval_on_selector_all("#about a", "e=>e.map(a=>a.href)")
        for must in ("https://4.dbt.io/api_key/request",
                     "https://www.faithcomesbyhearing.com/bible-brain/developer-documentation",
                     "https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain",
                     "https://www.faithcomesbyhearing.com/bible-brain/license"):
            self.assertIn(must, hrefs)
        rels = page.eval_on_selector_all("#about a", "e=>e.map(a=>a.rel)")
        self.assertTrue(all("noopener" in r for r in rels))

    # ---- resilience -------------------------------------------------------------------
    def test_proxy_failure_shows_message_not_a_blank_box(self):
        ctx = self.browser.new_context()
        self.addCleanup(ctx.close)
        page = ctx.new_page()
        html = PAGE.format(lang="en", body=READER)
        page.route(self.px.url + "/t.html", lambda r: r.fulfill(body=html, content_type="text/html"))
        page.route("**/bbp-proxy.php*", lambda r: r.fulfill(status=500, body='{"code":"bbp_error","message":"boom"}', content_type="application/json"))
        page.goto(self.px.url + "/t.html")
        page.wait_for_function("document.querySelector('#w') && document.querySelector('#w').innerText.length > 5", timeout=8000)
        self.assertTrue(page.inner_text("#w").strip())

    def test_no_horizontal_scroll_on_phone(self):
        p = self.open(READER)
        self.assertLessEqual(p.evaluate("document.documentElement.scrollWidth"), 420)


if __name__ == "__main__":
    unittest.main()
