#!/usr/bin/env python3
"""Run every check the project has, in one command:  python scripts/test_all.py [--fast]

--fast skips the browser tests (they need Playwright with Chromium).
"""
import glob, os, shutil, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PY = sys.executable
results = []


def step(name, cmd, cwd=ROOT, optional_tool=None):
    if optional_tool and not shutil.which(optional_tool):
        print("SKIP  %s (%s not installed)" % (name, optional_tool))
        results.append((name, "skip"))
        return
    r = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)
    ok = r.returncode == 0
    print("%s  %s" % ("ok  " if ok else "FAIL", name))
    if not ok:
        print((r.stdout + r.stderr).strip()[-2500:])
    results.append((name, "ok" if ok else "fail"))


def files(pattern):
    skip = (os.sep + "node_modules" + os.sep, os.sep + "dist" + os.sep, os.sep + ".git" + os.sep)
    return [f for f in glob.glob(os.path.join(ROOT, pattern), recursive=True) if not any(s in f for s in skip)]


def main():
    fast = "--fast" in sys.argv
    step("shared sources are copied into every package", [PY, "scripts/sync_shared.py", "--check"])
    step("translations are baked into the JS", [PY, "scripts/build_locales.py", "--check"])
    step("version numbers agree everywhere", [PY, "scripts/check_versions.py"])
    step("demo site assets are up to date", [PY, "scripts/build_site.py", "--check"])
    if shutil.which("php"):
        php_files = files("**/*.php")
        major = int(subprocess.run(["php", "-r", "echo PHP_MAJOR_VERSION;"], capture_output=True, text=True).stdout or 0)
        if major < 8:  # the Drupal module needs Drupal 10+, which needs PHP 8.1+ (it uses PHP attributes)
            php_files = [f for f in php_files if (os.sep + "drupal" + os.sep) not in f]
        bad = [f for f in php_files if subprocess.run(["php", "-l", f], capture_output=True).returncode != 0]
        print("%s  php -l on %d PHP files" % ("ok  " if not bad else "FAIL", len(php_files)))
        if bad:
            print("\n".join(bad))
        results.append(("php lint", "fail" if bad else "ok"))
    else:
        print("SKIP  php lint (php not installed)")
    if shutil.which("node"):
        bad = [f for f in files("**/*.js") if subprocess.run(["node", "--check", f], capture_output=True).returncode != 0]
        print("%s  node --check on %d JS files" % ("ok  " if not bad else "FAIL", len(files("**/*.js"))))
        results.append(("js syntax", "fail" if bad else "ok"))
    step("downloader tests", [PY, "-m", "unittest", "discover", "-s", "tests/downloader"])
    step("proxy tests (real PHP)", [PY, "-m", "unittest", "discover", "-s", "tests/php"], optional_tool="php")
    if not fast:
        step("browser tests (Chromium)", [PY, "-m", "unittest", "discover", "-s", "tests/js"], optional_tool="php")
    failed = [n for n, s in results if s == "fail"]
    print("\n%d checks, %d failed" % (len(results), len(failed)))
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
