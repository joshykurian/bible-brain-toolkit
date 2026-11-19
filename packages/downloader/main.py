#!/usr/bin/env python3
"""
Bible Brain Downloader
======================
Stand-alone desktop tool: choose a country and language, then download a Bible Brain
fileset (text or audio) as a single ZIP file.

Only the Python standard library is used. It also has a command line mode for scripts:
    python main.py --key KEY --country IN --list-filesets --language hin
    python main.py --key KEY --fileset HINBIBN2DA --out ./downloads
(the key can also come from the BIBLE_BRAIN_KEY environment variable)

To build a Windows .exe:
    pyinstaller --onefile --noconsole --icon=icon.ico main.py

Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Bible Brain is a project of Faith Comes By Hearing. You need your own free API key
(https://4.dbt.io/api_key/request). Check the licence and copyright of each Bible version before you
use or share what you download.
"""
import json
import os
import queue
import re
import socket
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
import zipfile
from datetime import datetime

try:
    import tkinter as tk
    from tkinter import filedialog, messagebox, ttk
except Exception:  # headless environments (tests) can still import the logic below
    tk = None

APP_NAME = "Bible Brain Downloader"
VERSION = "1.1.0"
API_BASE = os.environ.get("BIBLE_BRAIN_API_BASE", "https://4.dbt.io/api")
KEY_URL = "https://4.dbt.io/api_key/request"
SUPPORT_EMAIL = "support@digitalbibleplatform.com"
LINKS = [  # shown in the window (About) and by --about
    ("Bible Brain", "https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain"),
    ("Developer documentation", "https://www.faithcomesbyhearing.com/bible-brain/developer-documentation"),
    ("API reference", "https://www.faithcomesbyhearing.com/bible-brain/api-reference"),
    ("Request a free API key", KEY_URL),
    ("Available content", "https://www.faithcomesbyhearing.com/bible-brain-available-content"),
    ("License", "https://www.faithcomesbyhearing.com/bible-brain/license"),
    ("Terms and conditions", "https://www.faithcomesbyhearing.com/bible-brain/terms-conditions"),
    ("Copyright policy", "https://www.faithcomesbyhearing.com/copyright-policy"),
]
USER_AGENT = "BibleBrainDownloader/" + VERSION
CONFIG_PATH = os.path.join(os.path.expanduser("~"), ".bible_brain_downloader.json")

CANON = [(p.split(":")[0], int(p.split(":")[1])) for p in (
    "GEN:50 EXO:40 LEV:27 NUM:36 DEU:34 JOS:24 JDG:21 RUT:4 1SA:31 2SA:24 1KI:22 2KI:25 1CH:29 2CH:36 "
    "EZR:10 NEH:13 EST:10 JOB:42 PSA:150 PRO:31 ECC:12 SNG:8 ISA:66 JER:52 LAM:5 EZK:48 DAN:12 HOS:14 "
    "JOL:3 AMO:9 OBA:1 JON:4 MIC:7 NAM:3 HAB:3 ZEP:3 HAG:2 ZEC:14 MAL:4 MAT:28 MRK:16 LUK:24 JHN:21 "
    "ACT:28 ROM:16 1CO:16 2CO:13 GAL:6 EPH:6 PHP:4 COL:4 1TH:5 2TH:3 1TI:6 2TI:4 TIT:3 PHM:1 HEB:13 "
    "JAS:5 1PE:5 2PE:3 1JN:5 2JN:1 3JN:1 JUD:1 REV:22").split()]

# fileset type -> category used by the filter check boxes
CATEGORY = {
    "text_usx": "usx", "text_usfm": "usx",
    "text_plain": "text", "text_format": "text", "text_json": "text",
    "audio": "audio", "audio_drama": "audio",
    "audio_stream": "stream", "audio_drama_stream": "stream", "video_stream": "stream",
}
TEXT_TYPES = ("text_plain", "text_format", "text_json")
STORED_EXT = (".mp3", ".mp4", ".webm", ".opus", ".m4a", ".aac", ".ogg", ".zip")
SIZE_LABELS = {
    "C": "Complete Bible", "NT": "New Testament", "OT": "Old Testament",
    "NTP": "New Testament (partial)", "OTP": "Old Testament (partial)", "P": "Selected portions", "S": "Stories",
}


class ApiError(Exception):
    def __init__(self, message, code=0):
        Exception.__init__(self, message)
        self.code = code


class Cancelled(Exception):
    pass


# --------------------------------------------------------------------------------------
# API client
# --------------------------------------------------------------------------------------
class BBApi:
    def __init__(self, key, base=None, timeout=45):
        self.key = (key or "").strip()
        self.base = (base or API_BASE).rstrip("/")
        self.timeout = timeout

    def get(self, path, params=None, retries=3):
        q = {k: v for k, v in (params or {}).items() if v not in ("", None)}
        q["key"] = self.key
        q["v"] = 4
        url = self.base + path + "?" + urllib.parse.urlencode(q)
        last = None
        for attempt in range(retries):
            try:
                req = urllib.request.Request(url, headers={"Accept": "application/json", "User-Agent": USER_AGENT})
                with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                    return json.loads(resp.read().decode("utf-8"))
            except urllib.error.HTTPError as e:
                msg = "Bible Brain returned HTTP %d." % e.code
                try:
                    body = json.loads(e.read().decode("utf-8"))
                    msg = body["error"]["message"] or msg
                except Exception:
                    pass
                if e.code in (400, 401, 403, 404, 422):
                    raise ApiError(msg, e.code)
                last = ApiError(msg, e.code)
            except (urllib.error.URLError, socket.timeout, ConnectionError, ValueError) as e:
                reason = getattr(e, "reason", e)
                last = ApiError("Network problem: %s" % reason)
            time.sleep(1.5 * (attempt + 1))
        raise last or ApiError("Unknown error")

    def paged(self, path, params=None, max_pages=120, cancel=None):
        """Merge the 'data' rows of every page."""
        rows, page = [], 1
        while page <= max_pages:
            if cancel is not None and cancel.is_set():
                raise Cancelled()
            p = dict(params or {})
            p.update({"page": page, "limit": 50})
            r = self.get(path, p)
            data = r.get("data") or []
            rows.extend(data if isinstance(data, list) else [])
            pg = (r.get("meta") or {}).get("pagination") or {}
            last = int(pg.get("total_pages") or pg.get("last_page") or 1)
            if page >= last:
                break
            page += 1
        return rows

    def countries(self):
        out = []
        for c in self.paged("/countries"):
            codes = c.get("codes") or {}
            code = str(codes.get("iso_a2") or c.get("code") or c.get("id") or "").upper()
            if len(code) == 2 and c.get("name"):
                out.append((code, c["name"]))
        return sorted(set(out), key=lambda x: x[1].lower())

    def bibles_for_country(self, code):
        return self.paged("/bibles", {"country": code})


# --------------------------------------------------------------------------------------
# Catalogue helpers (pure functions, easy to test)
# --------------------------------------------------------------------------------------
def collect_filesets(raw):
    flat = []
    if not isinstance(raw, dict) and not isinstance(raw, list):
        return flat
    if isinstance(raw, dict) and "id" in raw:
        raw = [raw]
    items = raw.items() if isinstance(raw, dict) else [("", raw)]
    for asset, lst in items:
        if isinstance(lst, dict) and "id" in lst:
            lst = [lst]
        if not isinstance(lst, list):
            continue
        for fs in lst:
            if isinstance(fs, dict) and fs.get("id") and fs.get("type"):
                flat.append({"id": str(fs["id"]), "type": str(fs["type"]), "size": str(fs.get("size") or ""),
                             "asset": asset if isinstance(asset, str) else ""})
    return flat


def group_languages(bibles):
    langs = {}
    for b in bibles:
        if not isinstance(b, dict):
            continue
        iso = str(b.get("iso") or b.get("language_id") or "").lower()
        if not iso:
            continue
        english = b.get("language") or iso
        auto = b.get("autonym") or ""
        name = "%s (%s)" % (auto, english) if auto and auto.lower() != str(english).lower() else str(english)
        langs.setdefault(iso, {"id": iso, "name": name, "bibles": []})["bibles"].append(b)
    return sorted(langs.values(), key=lambda l: l["name"].lower())


def bible_title(b):
    vname, name, abbr = (b.get("vname") or "").strip(), (b.get("name") or "").strip(), b.get("abbr") or ""
    title = vname or name or abbr
    if vname and name and vname.lower() != name.lower():
        title = "%s - %s" % (vname, name)
    return "%s (%s)" % (title, abbr) if abbr and abbr not in title else title


def build_rows(lang, wanted):
    """Return (rows, total, hidden_counter). wanted is a set of categories."""
    rows, total, hidden = [], 0, {}
    for b in lang["bibles"]:
        for fs in collect_filesets(b.get("filesets")):
            total += 1
            cat = CATEGORY.get(fs["type"], "other")
            if cat in wanted:
                rows.append({"bible": bible_title(b), "abbr": b.get("abbr") or "", "type": fs["type"],
                             "contents": SIZE_LABELS.get(fs["size"].upper(), fs["size"] or "Full set"),
                             "fileset": fs["id"], "asset": fs["asset"]})
            else:
                hidden[fs["type"]] = hidden.get(fs["type"], 0) + 1
    rows.sort(key=lambda r: (r["bible"].lower(), r["type"], r["fileset"]))
    return rows, total, hidden


def extract_files(items):
    out, seen = [], set()

    def walk(x):
        if isinstance(x, dict):
            p = x.get("path") or x.get("url")
            if isinstance(p, str) and p.lower().startswith(("http://", "https://")):
                if p not in seen:
                    seen.add(p)
                    out.append(p)
                return
            for k in ("files", "data"):
                if isinstance(x.get(k), (list, dict)):
                    walk(x[k])
        elif isinstance(x, list):
            for i in x:
                walk(i)

    walk(items)
    return out


def safe_name(s):
    return re.sub(r"[^A-Za-z0-9._-]+", "_", s or "").strip("._") or "file"


def strip_tags(s):
    return re.sub(r"<[^>]+>", "", s or "")


# --------------------------------------------------------------------------------------
# Download job: builds one ZIP file
# --------------------------------------------------------------------------------------
class ZipJob:
    def __init__(self, api, out_dir, progress, cancel):
        self.api, self.out_dir, self.progress, self.cancel = api, out_dir, progress, cancel

    def check(self):
        if self.cancel.is_set():
            raise Cancelled()

    def run(self, fileset_id, ftype="", asset="", title="", abbr=""):
        fileset_id = fileset_id.strip()
        if not re.match(r"^[A-Za-z0-9_\-]{3,40}$", fileset_id):
            raise ApiError("That does not look like a fileset ID.")
        os.makedirs(self.out_dir, exist_ok=True)
        final = os.path.join(self.out_dir, safe_name(fileset_id) + ".zip")
        if os.path.exists(final):
            final = os.path.join(self.out_dir, "%s_%s.zip" % (safe_name(fileset_id), datetime.now().strftime("%Y%m%d_%H%M%S")))
        part = final + ".part"
        try:
            with zipfile.ZipFile(part, "w") as zf:
                count = self._fill(zf, fileset_id, ftype, asset)
                if count == 0:
                    raise ApiError("Nothing could be downloaded for %s. The licence may not allow downloads for this "
                                   "fileset, or the ID may be wrong." % fileset_id)
                self._add_info(zf, fileset_id, ftype, title, abbr, count)
            os.replace(part, final)
        except BaseException:
            try:
                os.remove(part)
            except OSError:
                pass
            raise
        return final

    # -- choose a strategy -------------------------------------------------------------
    def _fill(self, zf, fid, ftype, asset):
        if ftype in TEXT_TYPES:
            return self._crawl_text(zf, fid, ftype)
        self.progress(0, "Looking up files for %s ..." % fid)
        files = self._list_files(fid, ftype, asset)
        if files:
            return self._download_files(zf, fid, files)
        if not ftype or ftype in TEXT_TYPES:
            return self._crawl_text(zf, fid, ftype or "text_plain")
        return 0

    def _list_files(self, fid, ftype, asset):
        attempts = [("/download/" + fid, {}), ("/bibles/filesets/" + fid, {"type": ftype, "asset_id": asset})]
        for path, params in attempts:
            self.check()
            try:
                r = self.api.get(path, params)
            except ApiError as e:
                if e.code in (401, 403):
                    raise
                continue
            files = extract_files(r.get("data"))
            if not files:
                continue
            pg = (r.get("meta") or {}).get("pagination") or {}
            last = int(pg.get("total_pages") or pg.get("last_page") or 1)
            for page in range(2, last + 1):
                self.check()
                p = dict(params)
                p["page"] = page
                files += [f for f in extract_files(self.api.get(path, p).get("data")) if f not in files]
            return files
        return []

    def _download_files(self, zf, fid, files):
        used, n = set(), len(files)
        for i, url in enumerate(files):
            self.check()
            name = os.path.basename(urllib.parse.urlparse(url).path) or "file_%d" % (i + 1)
            base, ext = os.path.splitext(name)
            k = 1
            while name in used:
                k += 1
                name = "%s_%d%s" % (base, k, ext)
            used.add(name)
            self.progress(i / float(n), "Downloading %s  (%d of %d)" % (name, i + 1, n))
            data = self._fetch(url)
            zi = zipfile.ZipInfo("%s/%s" % (safe_name(fid), name), time.localtime()[:6])
            zi.compress_type = zipfile.ZIP_STORED if name.lower().endswith(STORED_EXT) else zipfile.ZIP_DEFLATED
            zf.writestr(zi, data)
        self.progress(1, "Files downloaded.")
        return n

    def _fetch(self, url):
        last = None
        for attempt in range(3):
            self.check()
            try:
                req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
                with urllib.request.urlopen(req, timeout=90) as resp:
                    return resp.read()
            except (urllib.error.URLError, socket.timeout, ConnectionError) as e:
                last = e
                time.sleep(1.5 * (attempt + 1))
        raise ApiError("Could not download a file: %s" % getattr(last, "reason", last))

    def _crawl_text(self, zf, fid, ftype):
        books = []
        try:
            r = self.api.get("/bibles/filesets/%s/books" % fid, {"type": ftype})
            for b in r.get("data") or []:
                chs = b.get("chapters") or []
                chs = [int(c["chapter"] if isinstance(c, dict) else c) for c in chs] if isinstance(chs, list) else list(range(1, int(chs) + 1))
                if b.get("book_id") and chs:
                    books.append((str(b["book_id"]).upper(), chs))
        except ApiError as e:
            if e.code in (401, 403):
                raise
        if not books:
            books = [(bid, list(range(1, n + 1))) for bid, n in CANON]
        total = sum(len(c) for _, c in books)
        done, written = 0, 0
        order = {bid: i + 1 for i, (bid, _) in enumerate(CANON)}
        for book, chapters in books:
            lines, got = [], False
            for ch in chapters:
                self.check()
                self.progress(done / float(total), "Reading %s %d  (%d of %d chapters)" % (book, ch, done + 1, total))
                done += 1
                try:
                    r = self.api.get("/bibles/filesets/%s/%s/%d" % (fid, book, ch), {"type": ftype})
                except ApiError as e:
                    if e.code in (401, 403):
                        raise
                    if not got and ch == chapters[0]:
                        break  # book not in this fileset
                    continue
                rows = r.get("data") or []
                if not rows:
                    continue
                got = True
                if ftype == "text_json":
                    lines.append(json.dumps(rows, ensure_ascii=False))
                    continue
                lines.append("Chapter %d" % ch)
                for v in rows:
                    txt = v.get("verse_text") or ""
                    txt = strip_tags(txt) if ftype == "text_format" else txt
                    lines.append("%s %s" % (v.get("verse_start_alt") or v.get("verse_start") or "", txt.strip()))
                lines.append("")
            if got:
                ext = ".json" if ftype == "text_json" else ".txt"
                zf.writestr("%s/%02d_%s%s" % (safe_name(fid), order.get(book, 99), book, ext), "\n".join(lines))
                written += 1
        self.progress(1, "Text downloaded.")
        return written

    def _add_info(self, zf, fid, ftype, title, abbr, count):
        copyright_text = ""
        if abbr:
            try:
                r = self.api.get("/bibles/%s/copyright" % abbr)
                d = r.get("data", r)
                d = d[0] if isinstance(d, list) and d else d
                if isinstance(d, dict):
                    c = d.get("copyright")
                    copyright_text = (c.get("copyright") if isinstance(c, dict) else c) or ""
            except Exception:
                pass
        zf.writestr("%s/README.txt" % safe_name(fid),
                    "Downloaded with %s %s on %s\n\nBible: %s\nFileset ID: %s\nType: %s\nFiles: %d\n\n"
                    "Copyright: %s\n\nPlease check the licence and copyright terms of this Bible version before you use, "
                    "share or publish these files. Content from Bible Brain (Faith Comes By Hearing).\n"
                    % (APP_NAME, VERSION, datetime.now().strftime("%Y-%m-%d %H:%M"), title or abbr or fid, fid,
                       ftype or "unknown", count, copyright_text or "see Bible Brain"))


# --------------------------------------------------------------------------------------
# Desktop GUI
# --------------------------------------------------------------------------------------
BG, PANEL, FG, MUTED = "#1b1411", "#251c18", "#efe6e1", "#a99a92"
ORANGE, ORANGE_HI, GREEN, RED, LINE = "#f7931e", "#ffab45", "#2fd89c", "#ff8a80", "#3a2e28"


class App:
    def __init__(self, root):
        self.root = root
        self.q = queue.Queue()
        self.cancel = threading.Event()
        self.busy = False
        self.countries = []
        self.langs = []
        self.rows = []
        self.row_buttons = []

        root.title(APP_NAME)
        root.configure(bg=BG)
        root.geometry("760x900")
        root.minsize(640, 760)
        self._style()
        self._build()
        self._load_config()
        root.after(120, self._poll)

    # ---- styling ----
    def _style(self):
        s = ttk.Style()
        try:
            s.theme_use("clam")
        except Exception:
            pass
        s.configure("TCombobox", fieldbackground=PANEL, background=PANEL, foreground=FG, arrowcolor=ORANGE,
                    bordercolor=LINE, lightcolor=LINE, darkcolor=LINE, padding=6)
        s.map("TCombobox", fieldbackground=[("readonly", PANEL)], foreground=[("readonly", FG)],
              selectbackground=[("readonly", PANEL)], selectforeground=[("readonly", FG)])
        s.configure("Orange.Horizontal.TProgressbar", troughcolor=PANEL, background=ORANGE, bordercolor=LINE,
                    lightcolor=ORANGE, darkcolor=ORANGE)
        s.configure("Vertical.TScrollbar", background=LINE, troughcolor=PANEL, arrowcolor=ORANGE, bordercolor=LINE)
        self.root.option_add("*TCombobox*Listbox.background", PANEL)
        self.root.option_add("*TCombobox*Listbox.foreground", FG)
        self.root.option_add("*TCombobox*Listbox.selectBackground", ORANGE)
        self.root.option_add("*TCombobox*Listbox.selectForeground", "#1b1411")

    def _label(self, parent, text, color=FG, size=10, bold=False, wrap=0, **kw):
        return tk.Label(parent, text=text, bg=kw.pop("bg", BG), fg=color, justify="left", anchor="w", wraplength=wrap,
                        font=("Segoe UI", size, "bold" if bold else "normal"), **kw)

    def _button(self, parent, text, cmd, primary=True, small=False):
        b = tk.Button(parent, text=text, command=cmd, relief="flat", cursor="hand2", bd=0,
                      bg=ORANGE if primary else PANEL, fg="#1b1411" if primary else FG,
                      activebackground=ORANGE_HI if primary else LINE, activeforeground="#1b1411" if primary else FG,
                      highlightthickness=0 if primary else 1, highlightbackground=LINE,
                      font=("Segoe UI", 8 if small else 10, "bold"), padx=8 if small else 14, pady=2 if small else 8)
        return b

    # ---- layout ----
    def _build(self):
        outer = tk.Frame(self.root, bg=BG)
        outer.pack(fill="both", expand=True, padx=22, pady=16)
        self._label(outer, "Bible Brain Downloader", ORANGE, 15, True).pack(anchor="w")
        self._label(outer, "Download Bible text and audio from the Bible Brain library as one ZIP file. "
                    "You need a free API key.", MUTED, 9, wrap=700).pack(anchor="w", pady=(2, 2))
        link = self._label(outer, "Request your free API key: " + KEY_URL, ORANGE, 9, cursor="hand2")
        link.bind("<Button-1>", lambda _e: webbrowser.open(KEY_URL))
        link.pack(anchor="w", pady=(0, 8))

        for t in ("1. Request your free API key at the address above (it is sent to your email) and copy it.",
                  "2. Paste the key in the box below and press Load countries and languages.",
                  "3. Choose a country, then a language. The filesets for that language are listed.",
                  "4. Press Download next to a fileset. You get a ZIP file. Check the licence of each Bible version before you use it."):
            self._label(outer, t, FG, 10, wrap=700).pack(anchor="w", pady=2)

        self._label(outer, "Bible Brain API key", FG, 10, True).pack(anchor="w", pady=(12, 3))
        krow = tk.Frame(outer, bg=BG)
        krow.pack(fill="x")
        self.key_var = tk.StringVar()
        self.key_entry = tk.Entry(krow, textvariable=self.key_var, show="*", bg=PANEL, fg=FG, insertbackground=FG,
                                  relief="flat", highlightthickness=1, highlightbackground=LINE, highlightcolor=ORANGE,
                                  font=("Segoe UI", 10))
        self.key_entry.pack(side="left", fill="x", expand=True, ipady=8, padx=(0, 12))
        self.load_btn = self._button(krow, "Load countries and languages", self.load_countries)
        self.load_btn.pack(side="left")
        opts = tk.Frame(outer, bg=BG)
        opts.pack(fill="x", pady=(4, 0))
        self.show_var = tk.BooleanVar(value=False)
        self.remember_var = tk.BooleanVar(value=False)
        for text, var, cmd in (("Show key", self.show_var, self._toggle_key),
                               ("Remember key on this computer", self.remember_var, None)):
            tk.Checkbutton(opts, text=text, variable=var, command=cmd, bg=BG, fg=MUTED, selectcolor=PANEL,
                           activebackground=BG, activeforeground=FG, highlightthickness=0,
                           font=("Segoe UI", 9)).pack(side="left", padx=(0, 14))

        sel = tk.Frame(outer, bg=BG)
        sel.pack(fill="x", pady=(12, 0))
        sel.columnconfigure(0, weight=1)
        sel.columnconfigure(1, weight=1)
        self._label(sel, "Country", FG, 10, True).grid(row=0, column=0, sticky="w")
        self._label(sel, "Language", FG, 10, True).grid(row=0, column=1, sticky="w", padx=(12, 0))
        self.country_cb = ttk.Combobox(sel, state="readonly", values=[])
        self.country_cb.grid(row=1, column=0, sticky="ew", pady=3)
        self.country_cb.bind("<<ComboboxSelected>>", lambda e: self.on_country())
        self.lang_cb = ttk.Combobox(sel, state="readonly", values=[])
        self.lang_cb.grid(row=1, column=1, sticky="ew", pady=3, padx=(12, 0))
        self.lang_cb.bind("<<ComboboxSelected>>", lambda e: self.render_rows())

        flt = tk.Frame(outer, bg=BG)
        flt.pack(fill="x", pady=(6, 0))
        self.cat_vars = {}
        for key, text, default in (("usx", "Text: USX / USFM", True), ("text", "Text: plain, formatted, JSON", False),
                                   ("audio", "Audio (MP3 / WebM)", True)):
            v = tk.BooleanVar(value=default)
            self.cat_vars[key] = v
            tk.Checkbutton(flt, text=text, variable=v, command=self.render_rows, bg=BG, fg=FG, selectcolor=PANEL,
                           activebackground=BG, activeforeground=FG, highlightthickness=0,
                           font=("Segoe UI", 9)).pack(side="left", padx=(0, 14))

        self.info_var = tk.StringVar(value="")
        self.info_lbl = tk.Label(outer, textvariable=self.info_var, bg=BG, fg=GREEN, justify="left", anchor="w",
                                 wraplength=700, font=("Segoe UI", 9, "bold"))
        self.info_lbl.pack(anchor="w", pady=(8, 4), fill="x")

        # results table (scrollable)
        head = tk.Frame(outer, bg=BG)
        head.pack(fill="x")
        self._table_cols(head, ("Bible", "Type", "Contents", "Fileset ID", ""), header=True)
        box = tk.Frame(outer, bg=PANEL, highlightthickness=1, highlightbackground=LINE)
        box.pack(fill="both", expand=True)
        self.canvas = tk.Canvas(box, bg=PANEL, highlightthickness=0, height=230)
        sb = ttk.Scrollbar(box, orient="vertical", command=self.canvas.yview)
        self.canvas.configure(yscrollcommand=sb.set)
        sb.pack(side="right", fill="y")
        self.canvas.pack(side="left", fill="both", expand=True)
        self.table = tk.Frame(self.canvas, bg=PANEL)
        self.table_win = self.canvas.create_window((0, 0), window=self.table, anchor="nw")
        self.table.bind("<Configure>", lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all")))
        self.canvas.bind("<Configure>", lambda e: self.canvas.itemconfigure(self.table_win, width=e.width))
        for w in (self.canvas, self.table):
            w.bind("<MouseWheel>", self._wheel)
            w.bind("<Button-4>", self._wheel)
            w.bind("<Button-5>", self._wheel)

        self._label(outer, "Bible Brain fileset ID", FG, 10, True).pack(anchor="w", pady=(12, 3))
        mrow = tk.Frame(outer, bg=BG)
        mrow.pack(fill="x")
        self.manual_var = tk.StringVar()
        tk.Entry(mrow, textvariable=self.manual_var, bg=PANEL, fg=FG, insertbackground=FG, relief="flat",
                 highlightthickness=1, highlightbackground=LINE, highlightcolor=ORANGE,
                 font=("Segoe UI", 10)).pack(side="left", fill="x", expand=True, ipady=8, padx=(0, 12))
        self.manual_btn = self._button(mrow, "Download from Bible Brain", self.download_manual, primary=False)
        self.manual_btn.pack(side="left")
        self._label(outer, "Or type a fileset ID yourself.", MUTED, 9).pack(anchor="w", pady=(2, 0))

        self._label(outer, "Save ZIP file in", FG, 10, True).pack(anchor="w", pady=(10, 3))
        orow = tk.Frame(outer, bg=BG)
        orow.pack(fill="x")
        downloads = os.path.join(os.path.expanduser("~"), "Downloads")
        self.out_var = tk.StringVar(value=downloads if os.path.isdir(downloads) else os.path.expanduser("~"))
        tk.Entry(orow, textvariable=self.out_var, bg=PANEL, fg=FG, insertbackground=FG, relief="flat",
                 highlightthickness=1, highlightbackground=LINE, highlightcolor=ORANGE,
                 font=("Segoe UI", 10)).pack(side="left", fill="x", expand=True, ipady=6, padx=(0, 12))
        self._button(orow, "Browse...", self.browse, primary=False).pack(side="left")

        self.progress = ttk.Progressbar(outer, style="Orange.Horizontal.TProgressbar", mode="determinate",
                                        maximum=100, length=200)
        self.progress.pack(fill="x", pady=(12, 4))
        prow = tk.Frame(outer, bg=BG)
        prow.pack(fill="x")
        self.status_var = tk.StringVar(value="No file downloaded yet.")
        self.status_lbl = tk.Label(prow, textvariable=self.status_var, bg=BG, fg=MUTED, anchor="w", justify="left",
                                   wraplength=520, font=("Segoe UI", 9))
        self.status_lbl.pack(side="left", fill="x", expand=True)
        self.cancel_btn = self._button(prow, "Cancel", self.cancel_job, primary=False, small=True)
        self.open_btn = self._button(prow, "Open folder", self.open_folder, primary=False, small=True)
        self.last_zip = ""

        about = tk.Frame(outer, bg=BG)
        about.pack(fill="x", pady=(14, 0))
        self._label(about, "About and links", FG, 9, True).pack(anchor="w")
        self._label(about, "Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Content is provided by Bible Brain, a project of Faith Comes By Hearing. "
                    "Please follow the license of every Bible version. Support: " + SUPPORT_EMAIL, MUTED, 8, wrap=700).pack(anchor="w", pady=(0, 3))
        row = tk.Frame(about, bg=BG)
        row.pack(fill="x")
        for i, (name, url) in enumerate(LINKS):
            lab = self._label(row, name, ORANGE, 9, cursor="hand2")
            lab.bind("<Button-1>", lambda _e, u=url: webbrowser.open(u))
            lab.grid(row=i // 4, column=i % 4, sticky="w", padx=(0, 18), pady=1)

    def _table_cols(self, parent, labels, header=False, widths=(32, 9, 16, 18, 10)):
        for i, t in enumerate(labels):
            tk.Label(parent, text=t, bg=BG if header else PANEL, fg=MUTED if header else FG, anchor="w", justify="left",
                     font=("Segoe UI", 9, "bold" if header else "normal"), width=widths[i],
                     wraplength=0 if header else (230 if i == 0 else 0)).grid(row=0, column=i, sticky="w", padx=4, pady=2)

    def _wheel(self, e):
        if getattr(e, "num", 0) == 4:
            self.canvas.yview_scroll(-2, "units")
        elif getattr(e, "num", 0) == 5:
            self.canvas.yview_scroll(2, "units")
        else:
            self.canvas.yview_scroll(int(-e.delta / 60) or (-1 if e.delta > 0 else 1), "units")

    # ---- small helpers ----
    def _toggle_key(self):
        self.key_entry.config(show="" if self.show_var.get() else "*")

    def _load_config(self):
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                cfg = json.load(f)
            if cfg.get("key"):
                self.key_var.set(cfg["key"])
                self.remember_var.set(True)
            if cfg.get("out") and os.path.isdir(cfg["out"]):
                self.out_var.set(cfg["out"])
        except Exception:
            pass

    def _save_config(self):
        try:
            cfg = {"out": self.out_var.get()}
            if self.remember_var.get():
                cfg["key"] = self.key_var.get().strip()
            with open(CONFIG_PATH, "w", encoding="utf-8") as f:
                json.dump(cfg, f)
            try:
                os.chmod(CONFIG_PATH, 0o600)  # only this user may read the saved key
            except OSError:
                pass
        except Exception:
            pass

    def set_status(self, msg, kind="info"):
        self.status_var.set(msg)
        self.status_lbl.config(fg={"info": MUTED, "ok": GREEN, "err": RED}.get(kind, MUTED))

    def set_busy(self, busy):
        self.busy = busy
        state = "disabled" if busy else "normal"
        for b in [self.load_btn, self.manual_btn] + self.row_buttons:
            try:
                b.config(state=state)
            except Exception:
                pass
        if busy:
            self.cancel_btn.pack(side="right", padx=(8, 0))
            self.open_btn.pack_forget()
        else:
            self.cancel_btn.pack_forget()

    def browse(self):
        d = filedialog.askdirectory(initialdir=self.out_var.get() or os.path.expanduser("~"))
        if d:
            self.out_var.set(d)

    def open_folder(self):
        path = os.path.dirname(self.last_zip) if self.last_zip else self.out_var.get()
        try:
            if sys.platform.startswith("win"):
                os.startfile(path)  # noqa
            elif sys.platform == "darwin":
                subprocess.Popen(["open", path])
            else:
                subprocess.Popen(["xdg-open", path])
        except Exception:
            pass

    # ---- actions ----
    def _api(self):
        key = self.key_var.get().strip()
        if not key:
            self.set_status("Paste your Bible Brain API key first.", "err")
            return None
        self._save_config()
        return BBApi(key)

    def load_countries(self):
        api = self._api()
        if not api or self.busy:
            return
        self.set_busy(True)
        self.set_status("Loading countries ...")
        self.info_var.set("")

        def work():
            try:
                self.q.put(("countries", api.countries()))
            except Exception as e:
                self.q.put(("error", str(e)))

        threading.Thread(target=work, daemon=True).start()

    def on_country(self):
        i = self.country_cb.current()
        api = self._api()
        if i < 0 or not api or self.busy:
            return
        code, name = self.countries[i]
        self.set_busy(True)
        self.set_status("Loading Bibles for %s ... this can take a minute for large countries." % name)
        self.lang_cb.set("")
        self.langs = []
        self.render_rows()

        def work():
            try:
                self.q.put(("bibles", (name, api.bibles_for_country(code))))
            except Exception as e:
                self.q.put(("error", str(e)))

        threading.Thread(target=work, daemon=True).start()

    def render_rows(self):
        for w in self.table.winfo_children():
            w.destroy()
        self.row_buttons = []
        i = self.lang_cb.current()
        if i < 0 or i >= len(self.langs):
            return
        lang = self.langs[i]
        wanted = {k for k, v in self.cat_vars.items() if v.get()}
        rows, total, hidden = build_rows(lang, wanted)
        self.rows = rows
        name = lang["name"]
        msg = "%d filesets found for %s. %d shown." % (total, name, len(rows))
        if hidden:
            msg += " Not shown: " + ", ".join("%s %d" % (k, v) for k, v in sorted(hidden.items())) + "."
        self.info_var.set(msg)
        for r, row in enumerate(rows):
            vals = (row["bible"], row["type"], row["contents"], row["fileset"])
            for c, t in enumerate(vals):
                tk.Label(self.table, text=t, bg=PANEL, fg=FG, anchor="w", justify="left", font=("Segoe UI", 9),
                         wraplength=230 if c == 0 else 0, width=(32, 9, 16, 18)[c]).grid(row=r * 2, column=c, sticky="w", padx=4, pady=4)
            b = self._button(self.table, "Download", lambda rw=row: self.download_row(rw), small=True)
            b.grid(row=r * 2, column=4, padx=6)
            self.row_buttons.append(b)
            tk.Frame(self.table, bg=LINE, height=1).grid(row=r * 2 + 1, column=0, columnspan=5, sticky="ew")
            for w in self.table.grid_slaves(row=r * 2):
                w.bind("<MouseWheel>", self._wheel)
        if not rows:
            tk.Label(self.table, text="Nothing to show. Tick another type above.", bg=PANEL, fg=MUTED,
                     font=("Segoe UI", 9)).grid(row=0, column=0, padx=8, pady=10)
        if self.busy:
            self.set_busy(True)

    def download_row(self, row):
        self._start_download(row["fileset"], row["type"], row["asset"], row["bible"], row["abbr"])

    def download_manual(self):
        fid = self.manual_var.get().strip()
        if not fid:
            self.set_status("Type a fileset ID first.", "err")
            return
        self._start_download(fid, "", "", fid, "")

    def _start_download(self, fid, ftype, asset, title, abbr):
        api = self._api()
        if not api or self.busy:
            return
        out = self.out_var.get().strip()
        if not out:
            self.set_status("Choose a folder for the ZIP file.", "err")
            return
        self.cancel.clear()
        self.set_busy(True)
        self.progress["value"] = 0
        self.set_status("Starting ...")

        def work():
            try:
                job = ZipJob(api, out, lambda f, m: self.q.put(("progress", (f, m))), self.cancel)
                self.q.put(("done", job.run(fid, ftype, asset, title, abbr)))
            except Cancelled:
                self.q.put(("cancelled", None))
            except Exception as e:
                self.q.put(("error", str(e)))

        threading.Thread(target=work, daemon=True).start()

    def cancel_job(self):
        self.cancel.set()
        self.set_status("Cancelling ...")

    # ---- thread results ----
    def _poll(self):
        try:
            while True:
                kind, payload = self.q.get_nowait()
                self._handle(kind, payload)
        except queue.Empty:
            pass
        self.root.after(120, self._poll)

    def _handle(self, kind, payload):
        if kind == "countries":
            self.countries = payload
            self.country_cb["values"] = [n for _, n in payload]
            self.set_busy(False)
            self.set_status("%d countries loaded. Choose a country." % len(payload), "ok")
        elif kind == "bibles":
            name, bibles = payload
            self.langs = group_languages(bibles)
            self.lang_cb["values"] = [l["name"] for l in self.langs]
            self.set_busy(False)
            self.set_status("%d languages found in %s. Choose a language." % (len(self.langs), name), "ok")
        elif kind == "progress":
            frac, msg = payload
            self.progress["value"] = max(0, min(100, frac * 100))
            self.set_status(msg)
        elif kind == "done":
            self.last_zip = payload
            self.progress["value"] = 100
            self.set_busy(False)
            self.set_status("Saved: " + payload, "ok")
            self.open_btn.pack(side="right", padx=(8, 0))
        elif kind == "cancelled":
            self.set_busy(False)
            self.progress["value"] = 0
            self.set_status("Cancelled. Nothing was saved.")
        elif kind == "error":
            self.set_busy(False)
            self.set_status(str(payload), "err")


def cli(argv):
    """Command line mode. Returns a process exit code."""
    import argparse

    ap = argparse.ArgumentParser(prog="bible-brain-downloader", description="Download Bible Brain filesets as ZIP files.")
    ap.add_argument("--key", default=os.environ.get("BIBLE_BRAIN_KEY", ""), help="Bible Brain API key (or set BIBLE_BRAIN_KEY)")
    ap.add_argument("--list-countries", action="store_true", help="list country codes")
    ap.add_argument("--country", help="two-letter country code, for example IN")
    ap.add_argument("--language", help="language code to filter by, for example hin")
    ap.add_argument("--list-filesets", action="store_true", help="list filesets for --country")
    ap.add_argument("--all-types", action="store_true", help="include streaming and video filesets in the list")
    ap.add_argument("--fileset", help="fileset ID to download")
    ap.add_argument("--type", default="", help="fileset type (audio, audio_drama, text_usx, text_plain, ...)")
    ap.add_argument("--out", default=".", help="folder for the ZIP file (default: current folder)")
    ap.add_argument("--about", action="store_true", help="show Bible Brain links (documentation, request a key, license)")
    ap.add_argument("--version", action="version", version="%s %s" % (APP_NAME, VERSION))
    a = ap.parse_args(argv)
    if a.about:
        print("%s %s\n%s\nContent is provided by Bible Brain, a project of Faith Comes By Hearing.\n" % (APP_NAME, VERSION, "Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing."))
        for name, url in LINKS:
            print("%-26s %s" % (name, url))
        print("%-26s %s" % ("Support", SUPPORT_EMAIL))
        return 0
    if not a.key:
        print("An API key is required: --key KEY or BIBLE_BRAIN_KEY. Request one free at " + KEY_URL, file=sys.stderr)
        return 2
    api = BBApi(a.key)
    try:
        if a.list_countries:
            for code, name in api.countries():
                print("%s\t%s" % (code, name))
            return 0
        if a.list_filesets:
            if not a.country:
                print("--list-filesets needs --country", file=sys.stderr)
                return 2
            langs = group_languages(api.bibles_for_country(a.country.upper()))
            if a.language:
                langs = [l for l in langs if l["id"] == a.language.lower()]
            wanted = set(CATEGORY.values()) | {"other"} if a.all_types else {"usx", "text", "audio"}
            for lang in langs:
                rows, _, _ = build_rows(lang, wanted)
                for r in rows:
                    print("%s\t%s\t%s\t%s\t%s" % (lang["id"], r["fileset"], r["type"], r["contents"], r["bible"]))
            return 0
        if a.fileset:
            cancel = threading.Event()
            last = [""]

            def progress(frac, msg):
                if msg != last[0]:
                    last[0] = msg
                    print("%3d%%  %s" % (int(frac * 100), msg), file=sys.stderr)

            path = ZipJob(api, a.out, progress, cancel).run(a.fileset, a.type, "", a.fileset, "")
            print(path)
            return 0
    except KeyboardInterrupt:
        print("Cancelled.", file=sys.stderr)
        return 130
    except (ApiError, Cancelled) as e:
        print("Error: %s" % e, file=sys.stderr)
        return 1
    ap.print_help()
    return 2


def main():
    if len(sys.argv) > 1:
        sys.exit(cli(sys.argv[1:]))
    if tk is None:
        print("Tkinter is not available in this Python installation. Use the command line mode: python main.py --help")
        sys.exit(1)
    root = tk.Tk()
    App(root)
    root.mainloop()


if __name__ == "__main__":
    main()
