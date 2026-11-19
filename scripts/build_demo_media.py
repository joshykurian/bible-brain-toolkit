#!/usr/bin/env python3
"""Generate the demo media for the GitHub Pages site (needs ffmpeg built with flite, the small speech synthesizer).
The files are synthetic: a computer voice reading the public-domain KJV sample text, and test-pattern video clips.
They are NOT Bible Brain recordings. Run only when you want to change the demo media; the results are committed."""
import json, os, shutil, subprocess, sys, tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "site", "media")
FONT = next((f for f in ("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf") if os.path.exists(f)), "")


def run(args):
    r = subprocess.run(["ffmpeg", "-y", "-loglevel", "error"] + args, capture_output=True, text=True)
    if r.returncode:
        sys.exit(r.stderr)


def verse_texts():
    """Read the sample verses from the demo shim itself, so the text and the voice can never drift apart."""
    js = os.path.join(ROOT, "site", "assets", "demo-api.js")
    code = ("var window={},document={currentScript:null,querySelector:function(){return null},createElement:function(){return{canPlayType:function(){return ''}}}};"
            "eval(require('fs').readFileSync(%s,'utf8'));console.log(JSON.stringify(window.BBP_DEMO.texts));" % json.dumps(js))
    r = subprocess.run(["node", "-e", code], capture_output=True, text=True)
    if r.returncode:
        sys.exit(r.stderr)
    return json.loads(r.stdout)


def duration(path):
    r = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "default=nw=1:nk=1", path], capture_output=True, text=True)
    return float(r.stdout.strip())


def voices(tmp_dir_unused=None):
    """One MP3 per passage, a short pause between verses, and the real start time of every verse."""
    if not shutil.which("node"):
        sys.exit("node is required (it reads the sample text from the demo)")
    names = {"JHN.3": "demo-john-3.mp3", "PSA.23": "demo-psalm-23.mp3"}
    timings = {}
    with tempfile.TemporaryDirectory() as tmp:
        run(["-f", "lavfi", "-i", "anullsrc=r=22050:cl=mono", "-t", "0.7", os.path.join(tmp, "gap.wav")])
        for key, verses in verse_texts().items():
            lines, starts, t = [], [], 0.0
            for i, text in enumerate(verses):
                wav = os.path.join(tmp, "%s-%02d.wav" % (key, i))
                safe = text.replace("\\", " ").replace("'", "\\'").replace(":", "\\:").replace("%", " percent")
                run(["-f", "lavfi", "-i", "flite=text='%s':voice=slt" % safe, "-ar", "22050", "-ac", "1", wav])
                starts.append(round(t, 2))
                t += duration(wav) + 0.7
                lines += ["file '%s'" % wav, "file '%s'" % os.path.join(tmp, "gap.wav")]
            lst = os.path.join(tmp, key + ".txt")
            with open(lst, "w") as fh:
                fh.write("\n".join(lines) + "\n")
            run(["-f", "concat", "-safe", "0", "-i", lst, "-af", "volume=0.95,afade=t=in:d=0.2,afade=t=out:st=%.2f:d=0.6" % (t - 0.8),
                 "-c:a", "libmp3lame", "-b:a", "40k", "-ac", "1", os.path.join(OUT, names[key])])
            timings[key] = starts
    with open(os.path.join(OUT, "demo-timings.json"), "w") as fh:
        json.dump(timings, fh)


def main():
    if not shutil.which("ffmpeg"):
        sys.exit("ffmpeg is required")
    os.makedirs(OUT, exist_ok=True)
    voices(tmp_dir_unused=None)
    for i, (color, label) in enumerate((("0x0E5A52", "Sample clip 1"), ("0x7E0E2A", "Sample clip 2")), 1):
        text = ("drawtext=fontfile=%s:text='%s':fontcolor=white:fontsize=34:x=(w-text_w)/2:y=h/2-40," % (FONT, label) +
                "drawtext=fontfile=%s:text='Generated test video - not Scripture':fontcolor=white@0.85:fontsize=20:x=(w-text_w)/2:y=h/2+10" % FONT) if FONT else "null"
        src = ["-f", "lavfi", "-i", "color=c=%s:s=640x360:r=15:d=12" % color, "-f", "lavfi", "-i", "sine=frequency=%d:duration=12" % (196 * i)]
        vf = ["-vf", text + ",format=yuv420p", "-af", "volume=0.12,afade=t=out:st=10.5:d=1.5"]
        run(src + vf + ["-c:v", "libx264", "-profile:v", "baseline", "-crf", "34", "-preset", "veryfast", "-c:a", "aac", "-b:a", "32k",
                        "-movflags", "+faststart", os.path.join(OUT, "demo-clip-%d.mp4" % i)])
        run(src + vf + ["-c:v", "libvpx-vp9", "-crf", "42", "-b:v", "0", "-deadline", "realtime", "-cpu-used", "8", "-c:a", "libopus", "-b:a", "24k",
                        os.path.join(OUT, "demo-clip-%d.webm" % i)])
    for f in sorted(os.listdir(OUT)):
        print("%-18s %5d KB" % (f, os.path.getsize(os.path.join(OUT, f)) // 1024))


if __name__ == "__main__":
    main()
