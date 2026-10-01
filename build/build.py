#!/usr/bin/env python3
"""Builds dist/pkg_lcookies-<version>.zip from src/.

Each extension folder in src/ (com_*, plg_*, mod_*) is zipped into packages/<name>.zip
and listed by src/pkg_lcookies.xml. Every .js/.css file in a media/ folder also gets a minified
copy (.min.js/.min.css, made with esbuild) next to it, as Joomla expects.

It also writes dist/pkg_lcookies.xml, the Joomla update server file (version, download URL of the
GitHub release, sha512). It is attached to every GitHub release, so the <updateservers> URL of
src/pkg_lcookies.xml (releases/latest/download/pkg_lcookies.xml) always gives the latest one.

Usage: npm install (once), then python3 build/build.py
Env: LC_DOWNLOAD_URL overrides the download URL (local tests of the update server).
"""

import hashlib
import html
import io
import os
import re
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "src"
DIST = ROOT / "dist"
EXTENSION_PREFIXES = ("com_", "plg_", "mod_")
ESBUILD = ROOT / "node_modules" / ".bin" / "esbuild"
REPOSITORY = "leocostarj22/lcookies"
# Regular expression matched against the Joomla version: 5.2 and later 5.x, and 6.x.
TARGET_PLATFORM = r"(5\.([2-9]|[1-9][0-9])|6\.[0-9]+)"
PHP_MINIMUM = "8.1"


def minified(path: Path, folder: Path) -> bytes | None:
    """Minified copy of a media asset, or None if the file is not one."""
    relative = path.relative_to(folder)
    if relative.parts[0] != "media" or path.suffix not in (".js", ".css") or ".min." in path.name:
        return None
    if not ESBUILD.exists():
        raise SystemExit("esbuild not found: run `npm install` first")
    result = subprocess.run(
        [str(ESBUILD), str(path), "--minify", "--legal-comments=none", "--log-level=warning"],
        capture_output=True,
        check=True,
    )
    return result.stdout


def zip_dir(folder: Path) -> bytes:
    """Returns the zipped contents of a folder (paths relative to it)."""
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(folder.rglob("*")):
            if path.is_file():
                relative = path.relative_to(folder)
                archive.write(path, relative.as_posix())
                code = minified(path, folder)
                if code is not None:
                    archive.writestr(relative.with_name(f"{path.stem}.min{path.suffix}").as_posix(), code)
    return buffer.getvalue()


def update_files(version: str, package: Path) -> None:
    """Writes dist/pkg_lcookies.xml."""
    sha512 = hashlib.sha512(package.read_bytes()).hexdigest()
    release = f"https://github.com/{REPOSITORY}/releases/tag/v{version}"
    download = os.environ.get("LC_DOWNLOAD_URL") or f"https://github.com/{REPOSITORY}/releases/download/v{version}/{package.name}"
    stability = "stable" if "-" not in version else re.sub(r"[^a-z]", "", version.split("-", 1)[1].lower()) or "beta"
    xml = f"""<?xml version="1.0" encoding="utf-8"?>
<updates>
\t<update>
\t\t<name>LCookies - Cookie Consent</name>
\t\t<description>LCookies {version}</description>
\t\t<element>pkg_lcookies</element>
\t\t<type>package</type>
\t\t<client>site</client>
\t\t<version>{version}</version>
\t\t<infourl title="LCookies {version}">{release}</infourl>
\t\t<downloads>
\t\t\t<downloadurl type="full" format="zip">{html.escape(download)}</downloadurl>
\t\t</downloads>
\t\t<sha512>{sha512}</sha512>
\t\t<tags>
\t\t\t<tag>{stability}</tag>
\t\t</tags>
\t\t<maintainer>Lcsilva</maintainer>
\t\t<maintainerurl>https://github.com/{REPOSITORY}</maintainerurl>
\t\t<targetplatform name="joomla" version="{TARGET_PLATFORM}"/>
\t\t<php_minimum>{PHP_MINIMUM}</php_minimum>
\t</update>
</updates>
"""
    (DIST / "pkg_lcookies.xml").write_text(xml, encoding="utf-8")


def main() -> None:
    manifest = (SRC / "pkg_lcookies.xml").read_text(encoding="utf-8")
    version = re.search(r"<version>([^<]+)</version>", manifest).group(1).strip()
    extensions = sorted(p for p in SRC.iterdir() if p.is_dir() and p.name.startswith(EXTENSION_PREFIXES))

    for ext in extensions:
        if f">{ext.name}.zip<" not in manifest:
            raise SystemExit(f"{ext.name} is not listed in pkg_lcookies.xml")

    DIST.mkdir(exist_ok=True)
    target = DIST / f"pkg_lcookies-{version}.zip"

    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as package:
        for path in sorted(SRC.rglob("*")):
            relative = path.relative_to(SRC)
            if path.is_file() and not relative.parts[0].startswith(EXTENSION_PREFIXES):
                package.write(path, relative.as_posix())
        for ext in extensions:
            package.writestr(f"packages/{ext.name}.zip", zip_dir(ext))

    update_files(version, target)

    print(f"Built {target.relative_to(ROOT)} ({', '.join(e.name for e in extensions)}) and dist/pkg_lcookies.xml")


if __name__ == "__main__":
    main()
