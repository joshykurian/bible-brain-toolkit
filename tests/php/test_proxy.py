"""Runs the real bbp-proxy.php under `php -S` against the mock Bible Brain API. Needs the php CLI."""
import json, os, shutil, sys, unittest, urllib.error, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, "tests"))
import helpers  # noqa: E402


@unittest.skipUnless(shutil.which("php"), "php CLI not installed")
class ProxyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.srv, cls.base = helpers.start_mock()
        cls.px = helpers.PhpProxy(cls.base)

    @classmethod
    def tearDownClass(cls):
        cls.px.stop()
        cls.srv.shutdown()

    def get(self, q, url=None, headers=None):
        req = urllib.request.Request((url or self.px.url) + "/bbp-proxy.php?ep=" + q, headers=headers or {})
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                return r.status, r.read().decode("utf-8"), r.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8"), e.headers

    def js(self, q, **kw):
        s, b, h = self.get(q, **kw)
        return s, json.loads(b)

    def test_status(self):
        s, d = self.js("status")
        self.assertEqual((s, d["ok"], d["key_set"]), (200, True, True))

    def test_countries_sorted(self):
        s, d = self.js("countries")
        self.assertEqual([c["code"] for c in d["countries"]], ["BT", "IN", "NP"])

    def test_catalog_filters_by_media(self):
        _, a = self.js("catalog&country=IN&media=audio")
        langs = {l["id"]: l for l in a["languages"]}
        self.assertIn("hin", langs)
        self.assertTrue(all(f["type"].startswith("audio") for v in langs["hin"]["versions"] for f in v["filesets"]))
        _, v = self.js("catalog&country=IN&media=video")
        self.assertEqual([l["id"] for l in v["languages"]], ["hin"])

    def test_catalog_empty_country(self):
        s, d = self.js("catalog&country=BT&media=text")
        self.assertEqual((s, d["languages"]), (200, []))

    def test_text_chapter(self):
        _, d = self.js("text&fileset=HINDOV&book=JHN&chapter=3&type=text_plain")
        self.assertEqual(d["chapter"], 3)
        self.assertIn("Nicodemus", d["verses"][0]["text"])

    def test_formatted_text_is_stripped_of_html(self):
        _, d = self.js("text&fileset=HINDOV&book=JHN&chapter=3&type=text_format")
        self.assertTrue(all("<" not in v["text"] for v in d["verses"]))
        self.assertIn("Nicodemus", d["verses"][0]["text"])

    def test_audio_and_timestamps(self):
        _, a = self.js("audio&fileset=HINDOVN2DA&book=JHN&chapter=3&type=audio_drama&asset=dbp-prod")
        self.assertTrue(a["urls"][0].endswith("ch3.wav?sig=1"))
        _, t = self.js("timestamps&fileset=HINDOVN2DA&book=JHN&chapter=3")
        self.assertEqual(len(t["times"]), 3)

    def test_video_clips_have_titles_and_books(self):
        _, d = self.js("video&fileset=HINDOVP2DV&asset=dbp-vid")
        self.assertEqual(len(d["clips"]), 3)
        self.assertEqual(d["clips"][0]["book_name"], "Mark")

    def test_validation_rejects_bad_input(self):
        for q in ["text&fileset=../x&book=JHN&chapter=3", "text&fileset=HINDOV&book=JHN", "text&fileset=HINDOV&book=ZZZZZZZZ&chapter=1",
                  "text&fileset=HINDOV&book=JHN&chapter=999", "catalog&country=INDIA"]:
            s, _, _ = self.get(q)
            self.assertEqual(s, 400, q)

    def test_unknown_endpoint(self):
        self.assertEqual(self.get("nope")[0], 404)

    def test_key_never_reaches_the_browser(self):
        for q in ["status", "countries", "catalog&country=IN&media=audio", "audio&fileset=HINDOVN2DA&book=JHN&chapter=3&type=audio_drama&asset=dbp-prod",
                  "nope", "text&fileset=../x&book=JHN&chapter=3"]:
            _, body, headers = self.get(q)
            self.assertNotIn(helpers.GOOD_KEY, body, q)
            self.assertNotIn(helpers.GOOD_KEY, str(headers), q)

    def test_responses_are_cached(self):
        helpers.MockBibleBrain.hits.clear()
        self.get("catalog&country=NP&media=text")
        first = len(helpers.MockBibleBrain.hits)
        self.get("catalog&country=NP&media=text")
        self.assertGreater(first, 0)
        self.assertEqual(len(helpers.MockBibleBrain.hits), first)

    def test_security_headers(self):
        _, _, h = self.get("status")
        self.assertEqual(h["X-Content-Type-Options"], "nosniff")
        self.assertIn("application/json", h["Content-Type"])

    def test_wrong_upstream_key_gives_clean_error(self):
        px = helpers.PhpProxy(self.base, key="WRONG")
        try:
            s, b, _ = self.get("countries", url=px.url)
            self.assertGreaterEqual(s, 400)
            self.assertIn("message", json.loads(b))
            self.assertNotIn("WRONG", b)
        finally:
            px.stop()

    def test_missing_key_message(self):
        px = helpers.PhpProxy(self.base, key="")
        try:
            s, b, _ = self.get("countries", url=px.url)
            self.assertEqual(s, 400)
            self.assertIn("API key", b)
        finally:
            px.stop()

    def test_origin_restriction(self):
        px = helpers.PhpProxy(self.base, extra_env={"BIBLE_BRAIN_ALLOWED_ORIGINS": "https://good.example"})
        try:
            _, _, h = self.get("status", url=px.url, headers={"Origin": "https://good.example"})
            self.assertEqual(h["Access-Control-Allow-Origin"], "https://good.example")
            _, _, h = self.get("status", url=px.url, headers={"Origin": "https://evil.example"})
            self.assertIsNone(h["Access-Control-Allow-Origin"])
        finally:
            px.stop()

    def test_open_origin_default(self):
        _, _, h = self.get("status")
        self.assertEqual(h["Access-Control-Allow-Origin"], "*")


if __name__ == "__main__":
    unittest.main()
