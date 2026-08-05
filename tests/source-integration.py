from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = root / "sabri-central-media"
required = {
    "includes/class-scm-upload-service.php",
    "includes/class-scm-processing-service.php",
    "includes/class-scm-delivery-service.php",
    "includes/class-scm-deletion-service.php",
    "includes/class-scm-record-store.php",
    "includes/class-scm-auth.php",
    "includes/class-scm-crypto.php",
    "includes/class-scm-rest.php",
}
missing = sorted(path for path in required if not (source / path).is_file())
assert not missing, f"missing source files: {missing}"

matrix = (root / "docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md").read_text(encoding="utf-8")
status = (root / "docs/runtime/STATUS.md").read_text(encoding="utf-8")
readme = (root / "README.md").read_text(encoding="utf-8")
assert "Complete: **0 / 33**" in matrix
assert "Partial: **20 / 33**" in matrix
assert "Missing: **13 / 33**" in matrix
assert "not 100% complete" in status
assert "Coded: **not complete**" in readme
assert "all code-level Must requirements" not in matrix
print("source-integration: PASS")
