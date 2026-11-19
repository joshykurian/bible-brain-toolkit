#!/usr/bin/env python3
"""Print the CHANGELOG.md section for one version (used by the release workflow)."""
import os, re, sys

root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
version = sys.argv[1] if len(sys.argv) > 1 else open(os.path.join(root, "VERSION")).read().strip()
text = open(os.path.join(root, "CHANGELOG.md"), encoding="utf-8").read()
m = re.search(r"^## \[?%s\]?.*?\n(.*?)(?=^## |\Z)" % re.escape(version), text, re.S | re.M)
print((m.group(1).strip() if m else "See CHANGELOG.md for details.") + "\n")
