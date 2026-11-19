"""Shared test helpers: a mock Bible Brain server and a PHP built-in server running the real proxy."""
import io, json, os, shutil, socket, subprocess, tempfile, threading, time, urllib.request, wave
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GOOD_KEY = "TESTKEY123"

JOHN3 = [  # King James Version (public domain)
    "There was a man of the Pharisees, named Nicodemus, a ruler of the Jews:",
    "The same came to Jesus by night, and said unto him, Rabbi, we know that thou art a teacher come from God: for no man can do these miracles that thou doest, except God be with him.",
    "Jesus answered and said unto him, Verily, verily, I say unto thee, Except a man be born again, he cannot see the kingdom of God.",
]


def wav_bytes(seconds=3, rate=8000):
    b = io.BytesIO()
    w = wave.open(b, "wb")
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(rate)
    w.writeframes(b"\x00\x00" * rate * seconds)
    w.close()
    return b.getvalue()


WAV = wav_bytes()


def fs(i, t, size):
    return {"id": i, "type": t, "size": size}


BIBLES_BY_COUNTRY = {
    "IN": [
        {"abbr": "ENGKJV", "name": "King James Version", "vname": "King James Version", "language": "English", "autonym": "English", "iso": "eng",
         "filesets": {"dbp-prod": [fs("ENGKJV", "text_plain", "C"), fs("ENGKJVN2DA", "audio_drama", "NT"), fs("ENGKJVN1DA", "audio", "NT"), fs("ENGKJVN_ET-usx", "text_usx", "NT")]}},
        {"abbr": "HINDOV", "name": "Hindi Old Version", "vname": "Hindi OV", "language": "Hindi", "autonym": "हिन्दी", "iso": "hin",
         "filesets": {"dbp-prod": [fs("HINDOV", "text_plain", "C"), fs("HINDOVN2DA", "audio_drama", "NT"), fs("HINDOVN_ET-usx", "text_usx", "NT"), fs("HINDOVN2SA", "audio_drama_stream", "NT")],
                      "dbp-vid": [fs("HINDOVP2DV", "video_stream", "P")]}},
    ],
    "NP": [
        {"abbr": "NEPNEP", "name": "Nepali Version", "vname": "Nepali Version", "language": "Nepali", "autonym": "नेपाली", "iso": "nep",
         "filesets": {"dbp-prod": [fs("NEPNEP", "text_plain", "C")]}},
    ],
    "BT": [],
}
COUNTRIES = [("IN", "India"), ("NP", "Nepal"), ("BT", "Bhutan")]
BOOKS = [{"book_id": "JHN", "name": "John", "chapters": list(range(1, 22))}, {"book_id": "MAT", "name": "Matthew", "chapters": list(range(1, 29))}]


class MockBibleBrain(BaseHTTPRequestHandler):
    base = ""
    hits = []

    def log_message(self, *a):
        pass

    def reply(self, code, obj=None, raw=None, ctype="application/json"):
        body = raw if raw is not None else json.dumps(obj).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        if self.path.startswith("/files/"):  # like a CDN: media may be loaded from any page
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Access-Control-Allow-Private-Network", "true")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        u = urlparse(self.path)
        q = {k: v[0] for k, v in parse_qs(u.query).items()}
        p = u.path
        MockBibleBrain.hits.append(p)
        if p.startswith("/files/"):
            if p.endswith(".wav"):
                return self.reply(200, raw=WAV, ctype="audio/wav")
            return self.reply(200, raw=b"BYTES-" + p.encode(), ctype="application/octet-stream")
        if q.get("key") != GOOD_KEY:
            return self.reply(401, {"error": {"message": "Invalid API key", "status_code": 401}})
        parts = [x for x in p.split("/") if x]  # api, ...
        if parts[:2] == ["api", "countries"]:
            page = int(q.get("page", 1))
            rows = [{"name": n, "codes": {"iso_a2": c}} for c, n in COUNTRIES]
            chunk = [rows[:2], rows[2:]][page - 1] if page <= 2 else []
            return self.reply(200, {"data": chunk, "meta": {"pagination": {"total_pages": 2}}})
        if parts[:2] == ["api", "bibles"] and len(parts) == 2:
            return self.reply(200, {"data": BIBLES_BY_COUNTRY.get(q.get("country", ""), []), "meta": {"pagination": {"total_pages": 1}}})
        if parts[:3] == ["api", "bibles", "filesets"]:
            fid = parts[3]
            rest = parts[4:]
            if not rest:  # fileset file list (audio/video/usx)
                if fid == "HINDOVP2DV":
                    clips = [
                        {"path": self.base + "/files/c1.mp4?sig=1", "book_id": "MRK", "title": "", "chapter_start": 1, "verse_start": 1, "verse_end": 8, "duration": 95},
                        {"path": self.base + "/files/c2.mp4?sig=1", "book_id": "MRK", "title": "Baptism of Jesus", "chapter_start": 1, "verse_start": 9, "verse_end": 13, "duration": 120},
                        {"path": self.base + "/files/c3.mp4?sig=1", "book_id": "LUK", "title": "", "chapter_start": 2, "verse_start": 1, "verse_end": 7, "duration": 80}]
                    return self.reply(200, {"data": clips})
                if fid == "EMPTYFS":
                    return self.reply(200, {"data": []})
                return self.reply(200, {"data": [{"path": self.base + "/files/%s_%02d.mp3?sig=1" % (fid, i)} for i in range(1, 4)]})
            if rest == ["books"]:
                return self.reply(200, {"data": BOOKS})
            if len(rest) == 2:
                book, ch = rest[0], int(rest[1])
                if q.get("type", "").startswith("audio"):
                    return self.reply(200, {"data": [{"path": self.base + "/files/ch%d.wav?sig=1" % ch}]})
                if book not in ("JHN", "MAT") or ch > 28:
                    return self.reply(404, {"error": {"message": "Not found"}})
                texts = JOHN3 if (book, ch) == ("JHN", 3) else ["Sample verse %d of %s %d" % (i, book, ch) for i in (1, 2, 3)]
                data = [{"book_name": "John" if book == "JHN" else "Matthew", "verse_start": i + 1, "verse_text": ("<p>%s</p>" % t) if q.get("type") == "text_format" else t} for i, t in enumerate(texts)]
                return self.reply(200, {"data": data})
        if parts[:2] == ["api", "download"]:
            return self.reply(200, {"data": [{"path": self.base + "/files/MAT.usx?sig=1"}, {"path": self.base + "/files/MRK.usx?sig=1"}]})
        if parts[:2] == ["api", "timestamps"]:
            return self.reply(200, {"data": [{"verse_start": i + 1, "timestamp": i * 0.6} for i in range(3)]})
        if len(parts) == 4 and parts[:2] == ["api", "bibles"] and parts[3] == "copyright":
            return self.reply(200, {"data": [{"copyright": {"copyright": "Public domain. Sample data for tests."}}]})
        return self.reply(404, {"error": {"message": "Unknown test route " + p}})


def start_mock():
    srv = ThreadingHTTPServer(("127.0.0.1", 0), MockBibleBrain)
    base = "http://127.0.0.1:%d" % srv.server_address[1]
    MockBibleBrain.base = base
    MockBibleBrain.hits = []
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    return srv, base


def free_port():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    port = s.getsockname()[1]
    s.close()
    return port


class PhpProxy:
    """Runs `php -S` over packages/html5-embed with the real bbp-proxy.php, pointed at the mock API."""

    def __init__(self, api_base, key=GOOD_KEY, extra_env=None):
        self.cache = tempfile.mkdtemp(prefix="bbp_cache_")
        self.port = free_port()
        env = dict(os.environ, BIBLE_BRAIN_KEY=key, BIBLE_BRAIN_API_BASE=api_base + "/api", BIBLE_BRAIN_CACHE_DIR=self.cache, PHP_CLI_SERVER_WORKERS="4", **(extra_env or {}))
        self.proc = subprocess.Popen(["php", "-S", "127.0.0.1:%d" % self.port, "-t", os.path.join(ROOT, "packages", "html5-embed")],
                                     env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.url = "http://127.0.0.1:%d" % self.port
        for _ in range(60):
            try:
                urllib.request.urlopen(self.url + "/bbp-proxy.php?ep=status", timeout=1).read()
                return
            except Exception:
                time.sleep(0.1)
        self.stop()
        raise RuntimeError("php -S did not start")

    def stop(self):
        self.proc.terminate()
        try:
            self.proc.wait(timeout=5)
        except Exception:
            self.proc.kill()
        shutil.rmtree(self.cache, ignore_errors=True)
