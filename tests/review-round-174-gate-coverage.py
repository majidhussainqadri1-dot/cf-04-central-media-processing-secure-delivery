#!/usr/bin/env python3
"""Fail closed when a sequential review regression exists but is absent from CI."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
quality = (root / "tools" / "quality-check.sh").read_text(encoding="utf-8")
test_dir = root / "tests"
php_tests = sorted(p for p in test_dir.glob("review-round*.php")
                   if p.name.startswith(("review-round-", "review-rounds-")))
py_tests = sorted(p for p in test_dir.glob("review-round*.py")
                  if p.name.startswith(("review-round-", "review-rounds-")))
php_calls = re.findall(
    r'^run_php "\$ROOT/(tests/review-rounds?-[^"]+\.php)"\s*$',
    quality, flags=re.MULTILINE,
)
py_calls = re.findall(
    r'^python3 "\$ROOT/(tests/review-rounds?-[^"]+\.py)"\s*$',
    quality, flags=re.MULTILINE,
)
expected_php = {p.relative_to(root).as_posix() for p in php_tests}
expected_py = {p.relative_to(root).as_posix() for p in py_tests}
problems = []
for label, expected, calls in (
    ("PHP", expected_php, php_calls),
    ("Python", expected_py, py_calls),
):
    missing = sorted(expected - set(calls))
    unexpected = sorted(set(calls) - expected)
    duplicate = sorted(p for p in set(calls) if calls.count(p) != 1)
    if missing:
        problems.append(f"{label} review tests not in quality gate: {missing}")
    if unexpected:
        problems.append(f"{label} gate references missing review tests: {unexpected}")
    if duplicate:
        problems.append(f"{label} review tests invoked more than once: {duplicate}")
if problems:
    for problem in problems:
        print("ROUND 174 FAIL:", problem, file=sys.stderr)
    raise SystemExit(1)
print(f"ROUND 174 GATE COVERAGE: PASS ({len(expected_php)} PHP, {len(expected_py)} Python review tests)")
