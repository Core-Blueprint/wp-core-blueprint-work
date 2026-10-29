#!/usr/bin/env python3
"""Golden regression: immutable Work ESM submodule URLs in every release ZIP."""
from __future__ import annotations

import importlib.util
import re
import shutil
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPT = ROOT / "tools" / "prepare-workspace-modules.py"
spec = importlib.util.spec_from_file_location("work_release_assets", SCRIPT)
assert spec and spec.loader
sys.dont_write_bytecode = True  # Keep checkout clean during Level 1.
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)

ASSETS = ROOT / "assets" / "js" / "workspace"
REQUIRED = mod.REQUIRED


def staged(parent: Path) -> Path:
    target = parent / "assets" / "js" / "workspace"
    target.mkdir(parents=True)
    for name in REQUIRED:
        shutil.copyfile(ASSETS / name, target / name)
    return parent


def validate(stage: Path) -> tuple[dict[str, str], bytes]:
    mapping = mod.prepare(stage)
    root = stage / "assets" / "js" / "workspace"
    assert len(mapping) == len(REQUIRED)
    entry = (root / "app.js").read_bytes()
    assert b"from './editors.js'" not in entry
    assert b"from './router.js'" not in entry
    assert not any("@cb-core/object-picker" in line for line in
                   entry.decode().splitlines() if line.lstrip().startswith("import "))
    for key, version in mapping.items():
        body = (root / version).read_text(encoding="utf-8")
        if key != "app.js":
            assert re.fullmatch(r"[a-z][a-z0-9-]*\.[a-f0-9]{16}\.js", version)
            assert not re.search(r"from ['\"]\./[a-z0-9-]+\.js['\"]", body), key
        for dep in re.findall(r"from ['\"]\./([^'\"]+\.js)['\"]", body):
            assert (root / dep).is_file(), (key, dep)
    return mapping, entry


with tempfile.TemporaryDirectory(prefix="cb-work-immutable-") as tmp:
    folder = Path(tmp)
    first, first_bytes = validate(staged(folder / "first"))
    again, again_bytes = validate(staged(folder / "again"))
    assert first == again and first_bytes == again_bytes

    altered = staged(folder / "altered")
    editor = altered / "assets" / "js" / "workspace" / "editors.js"
    editor.write_text(editor.read_text(encoding="utf-8") +
                      "\n// Golden fixture: a changed editor must change every importer URL.\n",
                      encoding="utf-8")
    second, second_bytes = validate(altered)
    assert second["editors.js"] != first["editors.js"]
    assert second_bytes != first_bytes

    missing = staged(folder / "missing")
    (missing / "assets" / "js" / "workspace" / "editors.js").unlink()
    try:
        mod.prepare(missing)
    except RuntimeError as error:
        assert "Missing Work workspace module" in str(error)
    else:
        raise AssertionError("Missing required ESM source did not fail closed.")

build = (ROOT / "tools" / "build-release").read_text(encoding="utf-8")
php = (ROOT / "src" / "Admin" / "Assets.php").read_text(encoding="utf-8")
assert 'python3 "$ROOT/tools/prepare-workspace-modules.py" "$STAGE"' in build
assert "hash_file( 'sha256', $app )" in php
assert "hash_file( 'sha256', $style )" in php
assert "self::VIEW_PREFERENCES_SCRIPT_HANDLE ]," in php  # immutable M1 deps

print("Work immutable ESM module graph: PASS (repeatable, recursive, cache-safe, fail-closed).")
