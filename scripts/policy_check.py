#!/usr/bin/env python3
"""Public-safe repository checks for the conditional CF-04 foundation."""

from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

FORBIDDEN_NAMES = {
    ".env",
    "id_rsa",
    "id_ed25519",
    "credentials.json",
    "service-account.json",
}

FORBIDDEN_SUFFIXES = {
    ".pem",
    ".key",
    ".p12",
    ".pfx",
}

FORBIDDEN_DIRECTORIES = {
    ".git",
    "node_modules",
    "vendor",
    "private",
    "secrets",
    "quarantine",
    "objects",
    "derivatives",
}

SECRET_PATTERNS = {
    "private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "AWS access key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "GitHub token": re.compile(r"\bgh[opusr]_[A-Za-z0-9]{30,}\b"),
    "reusable signed URL token": re.compile(r"https?://[^\s]+[?&](?:token|signature|sig|key)=[A-Za-z0-9_%+-]{16,}", re.I),
}

TEXT_SUFFIXES = {
    ".md",
    ".txt",
    ".json",
    ".yml",
    ".yaml",
    ".py",
    ".php",
    ".js",
    ".ts",
    ".css",
    ".xml",
}


def iter_repository_files() -> list[Path]:
    files: list[Path] = []
    for current_root, dirs, names in os.walk(ROOT):
        dirs[:] = [directory for directory in dirs if directory not in FORBIDDEN_DIRECTORIES]
        root_path = Path(current_root)
        for name in names:
            files.append(root_path / name)
    return files


def validate_json(path: Path, errors: list[str]) -> None:
    try:
        with path.open("r", encoding="utf-8") as handle:
            json.load(handle)
    except (OSError, UnicodeDecodeError, json.JSONDecodeError) as exc:
        errors.append(f"Invalid JSON: {path.relative_to(ROOT)}: {exc}")


def scan_text(path: Path, errors: list[str]) -> None:
    try:
        text = path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError) as exc:
        errors.append(f"Unreadable text file: {path.relative_to(ROOT)}: {exc}")
        return

    for label, pattern in SECRET_PATTERNS.items():
        if pattern.search(text):
            errors.append(f"Possible {label} found in {path.relative_to(ROOT)}")


def main() -> int:
    errors: list[str] = []

    for path in iter_repository_files():
        relative = path.relative_to(ROOT)

        if path.name in FORBIDDEN_NAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            errors.append(f"Forbidden credential-like file: {relative}")

        if path.suffix.lower() == ".json":
            validate_json(path, errors)

        if path.suffix.lower() in TEXT_SUFFIXES:
            scan_text(path, errors)

    if errors:
        print("CF-04 policy check failed:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1

    print("CF-04 policy check passed: JSON is valid and no prohibited public-repository material was detected.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
