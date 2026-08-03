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

SKIP_DIRECTORIES = {
    ".git",
    "node_modules",
    "vendor",
    "__pycache__",
}

FORBIDDEN_REPOSITORY_DIRECTORIES = {
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
    "reusable signed URL token": re.compile(
        r"https?://[^\s]+[?&](?:token|signature|sig|key)=[A-Za-z0-9_%+-]{16,}",
        re.I,
    ),
}

MAX_TEXT_SCAN_BYTES = 1_000_000


def iter_repository_files(errors: list[str]) -> list[Path]:
    files: list[Path] = []

    for current_root, dirs, names in os.walk(ROOT):
        root_path = Path(current_root)

        for directory in sorted(set(dirs) & FORBIDDEN_REPOSITORY_DIRECTORIES):
            errors.append(
                f"Forbidden public-repository directory: "
                f"{(root_path / directory).relative_to(ROOT)}"
            )

        dirs[:] = [
            directory
            for directory in dirs
            if directory not in SKIP_DIRECTORIES
            and directory not in FORBIDDEN_REPOSITORY_DIRECTORIES
        ]

        for name in names:
            files.append(root_path / name)

    return files


def validate_json(path: Path, errors: list[str]) -> None:
    try:
        with path.open("r", encoding="utf-8") as handle:
            document = json.load(handle)
    except (OSError, UnicodeDecodeError, json.JSONDecodeError) as exc:
        errors.append(f"Invalid JSON: {path.relative_to(ROOT)}: {exc}")
        return

    if path.name.endswith(".schema.json"):
        if not isinstance(document, dict):
            errors.append(f"JSON Schema must be an object: {path.relative_to(ROOT)}")
            return
        for required_key in ("$schema", "$id", "title", "type", "properties"):
            if required_key not in document:
                errors.append(
                    f"JSON Schema missing {required_key}: {path.relative_to(ROOT)}"
                )


def scan_text(path: Path, errors: list[str]) -> None:
    try:
        if path.stat().st_size > MAX_TEXT_SCAN_BYTES:
            return
        text = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return
    except OSError as exc:
        errors.append(f"Unreadable file: {path.relative_to(ROOT)}: {exc}")
        return

    for label, pattern in SECRET_PATTERNS.items():
        if pattern.search(text):
            errors.append(f"Possible {label} found in {path.relative_to(ROOT)}")


def main() -> int:
    errors: list[str] = []

    for path in iter_repository_files(errors):
        relative = path.relative_to(ROOT)
        lower_name = path.name.lower()

        if lower_name == ".env" or (
            lower_name.startswith(".env.") and lower_name != ".env.example"
        ):
            errors.append(f"Forbidden environment file: {relative}")

        if path.name in FORBIDDEN_NAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            errors.append(f"Forbidden credential-like file: {relative}")

        if path.suffix.lower() == ".json":
            validate_json(path, errors)

        scan_text(path, errors)

    if errors:
        print("CF-04 policy check failed:", file=sys.stderr)
        for error in sorted(set(errors)):
            print(f"- {error}", file=sys.stderr)
        return 1

    print(
        "CF-04 policy check passed: contracts are valid JSON and no prohibited "
        "public-repository material was detected."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
