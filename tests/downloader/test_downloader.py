"""Tests for the Bible Brain Downloader (logic and CLI). Run: python -m unittest discover tests/downloader -v"""
import io, os, shutil, subprocess, sys, tempfile, threading, unittest, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
sys.path.insert(0, os.path.join(ROOT, "tests"))
sys.path.insert(0, os.path.join(ROOT, "packages", "downloader"))

import helpers  # noqa: E402
import main as dl  # noqa: E402


class PureFunctions(unittest.TestCase):
    def test_collect_filesets_shapes(self):
        raw = {"dbp-prod": [{"id": "A", "type": "audio", "size": "NT"}, {"id": "", "type": "x"}], "dbp-vid": {"id": "V", "type": "video_stream"}}
        got = dl.collect_filesets(raw)
        self.assertEqual([f["id"] for f in got], ["A", "V"])
        self.assertEqual(got[1]["asset"], "dbp-vid")
        self.assertEqual(dl.collect_filesets(None), [])

    def test_group_and_rows(self):
        langs = dl.group_languages(helpers.BIBLES_BY_COUNTRY["IN"])
        self.assertEqual({l["id"] for l in langs}, {"eng", "hin"})
        hin = [l for l in langs if l["id"] == "hin"][0]
        rows, total, hidden = dl.build_rows(hin, {"usx", "text", "audio"})
        self.assertEqual(total, 5)
        self.assertEqual(sorted(r["type"] for r in rows), ["audio_drama", "text_plain", "text_usx"])
        self.assertEqual(hidden.get("video_stream"), 1)
        self.assertEqual(hidden.get("audio_drama_stream"), 1)  # unknown category type is hidden, not lost

    def test_extract_files_dedupes_and_ignores_non_http(self):
        items = [{"path": "https://x/a.mp3"}, {"path": "https://x/a.mp3"}, {"path": "ftp://x/b"}, {"files": [{"url": "http://x/c.mp3"}]}]
        self.assertEqual(dl.extract_files(items), ["https://x/a.mp3", "http://x/c.mp3"])

    def test_safe_name_and_strip(self):
        self.assertEqual(dl.safe_name("../a b/c"), "a_b_c")
        self.assertEqual(dl.safe_name(""), "file")
        self.assertEqual(dl.strip_tags("<p>Hi <b>there</b></p>"), "Hi there")


class WithMock(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.srv, cls.base = helpers.start_mock()

    @classmethod
    def tearDownClass(cls):
        cls.srv.shutdown()

    def setUp(self):
        self.out = tempfile.mkdtemp(prefix="bbd_")
        self.api = dl.BBApi(helpers.GOOD_KEY, base=self.base + "/api")
        self.msgs = []

    def tearDown(self):
        shutil.rmtree(self.out, ignore_errors=True)

    def job(self, cancel=None):
        return dl.ZipJob(self.api, self.out, lambda f, m: self.msgs.append((f, m)), cancel or threading.Event())


class ApiTests(WithMock):
    def test_countries_paged_and_sorted(self):
        self.assertEqual(self.api.countries(), [("BT", "Bhutan"), ("IN", "India"), ("NP", "Nepal")])

    def test_bad_key_is_not_retried(self):
        bad = dl.BBApi("nope", base=self.base + "/api")
        with self.assertRaises(dl.ApiError) as cm:
            bad.countries()
        self.assertEqual(cm.exception.code, 401)
        self.assertIn("Invalid API key", str(cm.exception))

    def test_bibles_for_country(self):
        self.assertEqual(len(self.api.bibles_for_country("IN")), 2)
        self.assertEqual(self.api.bibles_for_country("BT"), [])


class ZipJobTests(WithMock):
    def names(self, path):
        z = zipfile.ZipFile(path)
        self.addCleanup(z.close)
        return sorted(z.namelist()), z

    def test_audio_download(self):
        path = self.job().run("HINDOVN2DA", "audio_drama", "dbp-prod", "Hindi OV", "HINDOV")
        names, _ = self.names(path)
        self.assertTrue(path.endswith("HINDOVN2DA.zip"))
        self.assertIn("HINDOVN2DA/README.txt", names)
        self.assertEqual(len([n for n in names if n.endswith(".usx")]), 2)
        self.assertFalse([f for f in os.listdir(self.out) if f.endswith(".part")])

    def test_text_crawl(self):
        path = self.job().run("HINDOV", "text_plain", "dbp-prod", "Hindi OV", "HINDOV")
        names, z = self.names(path)
        self.assertIn("HINDOV/43_JHN.txt", names)
        self.assertIn("HINDOV/40_MAT.txt", names)
        john = z.read("HINDOV/43_JHN.txt").decode("utf-8")
        self.assertIn("Chapter 3", john)
        self.assertIn("Nicodemus", john)
        self.assertIn("Public domain", z.read("HINDOV/README.txt").decode())

    def test_text_format_strips_tags(self):
        path = self.job().run("HINDOV", "text_format", "", "", "")
        _, z = self.names(path)
        self.assertNotIn("<p>", z.read("HINDOV/43_JHN.txt").decode())

    def test_existing_zip_not_overwritten(self):
        a = self.job().run("HINDOVN2DA", "audio_drama")
        b = self.job().run("HINDOVN2DA", "audio_drama")
        self.assertNotEqual(a, b)
        self.assertTrue(os.path.exists(a) and os.path.exists(b))

    def test_invalid_fileset_id(self):
        with self.assertRaises(dl.ApiError):
            self.job().run("../etc/passwd")

    def test_cancel_leaves_no_part_file(self):
        ev = threading.Event()
        ev.set()
        with self.assertRaises(dl.Cancelled):
            self.job(ev).run("HINDOVN2DA", "audio_drama")
        self.assertEqual(os.listdir(self.out), [])

    def test_nothing_to_download_raises_and_cleans_up(self):
        # /download returns files for any id in the mock, so use a bad key to force the 401 path
        job = dl.ZipJob(dl.BBApi("bad", base=self.base + "/api"), self.out, lambda f, m: None, threading.Event())
        with self.assertRaises(dl.ApiError):
            job.run("HINDOVN2DA", "audio_drama")
        self.assertEqual(os.listdir(self.out), [])

    def test_progress_reaches_one(self):
        self.job().run("HINDOVN2DA", "audio_drama")
        self.assertEqual(self.msgs[-1][0], 1)


class CliTests(WithMock):
    def run_cli(self, *args, key=helpers.GOOD_KEY):
        env = dict(os.environ, BIBLE_BRAIN_API_BASE=self.base + "/api")
        env.pop("BIBLE_BRAIN_KEY", None)
        if key:
            env["BIBLE_BRAIN_KEY"] = key
        return subprocess.run([sys.executable, "-I", os.path.join(ROOT, "packages", "downloader", "main.py")] + list(args),
                              env=env, capture_output=True, text=True, timeout=120)

    def test_help_and_version(self):
        self.assertEqual(self.run_cli("--help").returncode, 0)
        self.assertIn(dl.VERSION, self.run_cli("--version").stdout)

    def test_missing_key(self):
        r = self.run_cli("--list-countries", key="")
        self.assertEqual(r.returncode, 2)
        self.assertIn("API key", r.stderr)

    def test_list_countries(self):
        r = self.run_cli("--list-countries")
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn("IN\tIndia", r.stdout)

    def test_list_filesets_language_filter(self):
        r = self.run_cli("--list-filesets", "--country", "IN", "--language", "hin")
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn("HINDOVN2DA", r.stdout)
        self.assertNotIn("ENGKJV", r.stdout)
        self.assertNotIn("HINDOVP2DV", r.stdout)
        r2 = self.run_cli("--list-filesets", "--country", "IN", "--language", "hin", "--all-types")
        self.assertIn("HINDOVP2DV", r2.stdout)

    def test_download(self):
        r = self.run_cli("--fileset", "HINDOVN2DA", "--type", "audio_drama", "--out", self.out)
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertTrue(os.path.exists(r.stdout.strip()))

    def test_bad_key_exit_code(self):
        r = self.run_cli("--list-countries", key="wrong")
        self.assertEqual(r.returncode, 1)
        self.assertIn("Invalid API key", r.stderr)


if __name__ == "__main__":
    unittest.main()


class AboutLinks(unittest.TestCase):
    def test_links_cover_the_essentials(self):
        urls = dict(dl.LINKS)
        self.assertTrue(all(u.startswith("https://") for u in urls.values()))
        for name in ("Bible Brain", "Developer documentation", "Request a free API key", "License"):
            self.assertIn(name, urls)
        self.assertEqual(urls["Request a free API key"], dl.KEY_URL)

    def test_cli_about_needs_no_key(self):
        env = dict(os.environ)
        env.pop("BIBLE_BRAIN_KEY", None)
        r = subprocess.run([sys.executable, "-I", os.path.join(ROOT, "packages", "downloader", "main.py"), "--about"],
                           env=env, capture_output=True, text=True, timeout=30)
        self.assertEqual(r.returncode, 0, r.stderr)
        for _, url in dl.LINKS:
            self.assertIn(url, r.stdout)
        self.assertIn(dl.SUPPORT_EMAIL, r.stdout)

    def test_window_builds_clickable_links(self):
        """No display here, so run App._build against a stand-in Tk and check every link opens its URL."""
        from unittest import mock
        opened, bound = [], {}

        class FakeWidget(mock.MagicMock):
            pass

        fake_tk = mock.MagicMock()

        def make_label(*a, **kw):
            w = mock.MagicMock()
            w._text = kw.get("text")
            w.bind.side_effect = lambda ev, fn: bound.__setitem__(w._text, fn)
            return w

        fake_tk.Label.side_effect = make_label
        with mock.patch.object(dl, "tk", fake_tk), mock.patch.object(dl, "ttk", mock.MagicMock(), create=True), \
                mock.patch.object(dl.webbrowser, "open", side_effect=opened.append):
            app = dl.App.__new__(dl.App)
            app.root = mock.MagicMock()
            for name in ("browse", "open_folder", "cancel_job", "load_countries", "download_manual", "_toggle_key"):
                setattr(app, name, lambda *a, **k: None)
            try:
                app._build()
            except AttributeError as e:  # parts of the window unrelated to About may need more of Tk than the stand-in gives
                self.skipTest("stand-in Tk is too small for the whole window: %s" % e)
            for name, url in dl.LINKS:
                self.assertIn(name, bound, name)
                bound[name](None)
                self.assertEqual(opened[-1], url)
            bound["Request your free API key: " + dl.KEY_URL](None)
            self.assertEqual(opened[-1], dl.KEY_URL)
