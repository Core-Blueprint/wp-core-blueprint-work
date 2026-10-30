#!/usr/bin/env python3
"""Build immutable, content-addressed ESM imports for the packaged Work app.

Only the temporary release stage is modified. Keep the source checkout readable
for tests and development. The main app.js is a stable entry URL whose ?ver
fingerprint is computed from the rewritten bytes by Admin/Assets.php.
Its imported workspace modules use immutable content-hashed filenames.
"""
from __future__ import annotations

import hashlib
import re
import sys
from pathlib import Path

RELATIVE_IMPORT = re.compile(
    r"(?m)^(?P<start>[ \t]*import[ \t]+(?:[^\n;]*?[ \t]+from[ \t]+)?['\"])"
    r"(?P<name>\./[a-z][a-z0-9-]*\.js)"
    r"(?P<end>['\"][ \t]*;?[ \t]*)$"
)
REQUIRED = (
    "app.js", "router.js", "state.js", "data-client.js", "canonical-mount.js",
    "canonical-filters.js", "canonical-mutations.js", "editors.js",
    "operations.js", "engagements.js", "engagement-rules.js", "engagement-billing.js",
)


def prepare(stage: Path) -> dict[str, str]:
    root = stage / "assets" / "js" / "workspace"
    original: dict[str, str] = {}
    for name in REQUIRED:
        file = root / name
        if not file.is_file():
            raise RuntimeError(f"Missing Work workspace module: {name}")
        original[name] = file.read_text(encoding="utf-8")

    versions: dict[str, str] = {}
    visiting: set[str] = set()

    def rewrite(name: str) -> str:
        if name in versions:
            return versions[name]
        if name in visiting:
            raise RuntimeError("Circular Work workspace import: " + name)
        if name not in original:
            raise RuntimeError("Unregistered Work workspace module: " + name)
        visiting.add(name)

        def convert(match: re.Match[str]) -> str:
            dependency = match.group("name")[2:]
            version = rewrite(dependency)
            return match.group("start") + "./" + version + match.group("end")

        result = RELATIVE_IMPORT.sub(convert, original[name])
        visiting.remove(name)
        if name == "app.js":
            (root / name).write_text(result, encoding="utf-8")
            versions[name] = name
        else:
            digest = hashlib.sha256(result.encode("utf-8")).hexdigest()[:16]
            immutable_name = name[:-3] + "." + digest + ".js"
            (root / immutable_name).write_text(result, encoding="utf-8")
            versions[name] = immutable_name
        return versions[name]

    rewrite("app.js")
    if len(versions) != len(REQUIRED):
        raise RuntimeError("Unreachable Work workspace module detected")
    return versions


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: prepare-workspace-modules.py <staged-plugin-root>", file=sys.stderr)
        return 2
    paths = prepare(Path(sys.argv[1]))
    for key in sorted(paths):
        print(f"[workspace-assets] {key} -> {paths[key]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
