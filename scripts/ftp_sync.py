#!/usr/bin/env python3
"""
Incremental FTP sync for the project.

Rules:
- Sync all files from repo root to remote path with the same layout.
- Skip public chat message store to avoid pushing live message data.
- Record hashes in .ftp_sync_state.json to only upload changed/new files.
"""
import hashlib
import json
import os
import posixpath
import time
from ftplib import FTP
from pathlib import Path
from typing import Dict, Iterable

# FTP credentials (provided by user)
FTP_HOST = "156.238.240.88"
FTP_PORT = 21
FTP_USER = "bt79318528_ReXDza"
FTP_PASSWORD = "79c9f5d9ac29e8"

# Paths and settings
ROOT = Path(__file__).resolve().parent.parent
STATE_FILE = ROOT / ".ftp_sync_state.json"
# Skip public chat data and volatile/generated folders
EXCLUDE_PATHS = {
    "data/messages.json",           # public chat messages
    ".user.ini",
    ".ftp_sync_state.json",
    "tmp_chat_js_check.js",
}
EXCLUDE_DIRS = {
    ".git",
    "node_modules",
    "__pycache__",
    "data",
    "uploads",
    "logs",
}

# Permission settings (some hosts create 600/700 by default)
DIR_MODE = os.getenv("FTP_SYNC_DIR_MODE", "755")
FILE_MODE = os.getenv("FTP_SYNC_FILE_MODE", "644")
FORCE_UPLOAD = os.getenv("FTP_SYNC_FORCE", "0") == "1"

# Retry behavior (for unstable networks/FTP).
# FTP_SYNC_MAX_RETRIES=0 means retry forever (default).
MAX_CONNECT_RETRIES = int(os.getenv("FTP_SYNC_MAX_RETRIES", "0"))
INITIAL_BACKOFF_S = float(os.getenv("FTP_SYNC_INITIAL_BACKOFF_S", "2.0"))
MAX_BACKOFF_S = float(os.getenv("FTP_SYNC_MAX_BACKOFF_S", "60.0"))
BACKOFF_MULTIPLIER = float(os.getenv("FTP_SYNC_BACKOFF_MULTIPLIER", "1.6"))


def iter_files(base: Path) -> Iterable[Path]:
    for path in base.rglob("*"):
        if path.is_dir():
            if path.name in EXCLUDE_DIRS:
                continue
        elif path.is_file():
            rel = path.relative_to(base).as_posix()
            if rel.startswith("."):  # skip hidden files at root
                continue
            if any(rel == ex or rel.startswith(ex.rstrip("*")) for ex in EXCLUDE_PATHS):
                continue
            if rel.startswith("data/rate_"):
                continue
            if any(part in EXCLUDE_DIRS for part in path.parts):
                continue
            yield path


def md5sum(path: Path) -> str:
    h = hashlib.md5()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


def load_state() -> Dict[str, str]:
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text())
        except Exception:
            return {}
    return {}


def save_state(state: Dict[str, str]) -> None:
    STATE_FILE.write_text(json.dumps(state, indent=2))


def ensure_remote_dirs(ftp: FTP, remote_path: str) -> None:
    # Walk path parts to create directories as needed
    parts = remote_path.strip("/").split("/")
    for i in range(len(parts) - 1):
        subdir = "/".join(parts[: i + 1])
        try:
            ftp.mkd(subdir)
        except Exception:
            pass  # already exists
        try:
            ftp.sendcmd(f"SITE CHMOD {DIR_MODE} {subdir}")
        except Exception:
            pass


def upload_file(ftp: FTP, local_path: Path, remote_path: str) -> None:
    ensure_remote_dirs(ftp, remote_path)
    with local_path.open("rb") as f:
        ftp.storbinary(f"STOR {remote_path}", f)
    try:
        ftp.sendcmd(f"SITE CHMOD {FILE_MODE} {remote_path}")
    except Exception:
        pass


def connect_with_retries() -> FTP:
    attempt = 0
    backoff = INITIAL_BACKOFF_S

    while True:
        attempt += 1
        ftp = FTP()
        try:
            ftp.connect(FTP_HOST, FTP_PORT, timeout=15)
            ftp.login(FTP_USER, FTP_PASSWORD)
            return ftp
        except KeyboardInterrupt:
            try:
                ftp.close()
            except Exception:
                pass
            raise
        except Exception as exc:
            try:
                ftp.close()
            except Exception:
                pass

            if MAX_CONNECT_RETRIES and attempt >= MAX_CONNECT_RETRIES:
                raise

            print(
                f"Connect/login failed (attempt {attempt}): {exc}. "
                f"Retrying in {backoff:.1f}s..."
            )
            time.sleep(backoff)
            backoff = min(MAX_BACKOFF_S, backoff * BACKOFF_MULTIPLIER)


def main() -> None:
    prev_state = load_state()
    new_state: Dict[str, str] = {}
    changed: Dict[str, Path] = {}

    for path in iter_files(ROOT):
        rel = path.relative_to(ROOT).as_posix()
        digest = md5sum(path)
        new_state[rel] = digest
        if FORCE_UPLOAD or prev_state.get(rel) != digest:
            changed[rel] = path

    if not changed:
        print("No changes to sync.")
        return

    print(f"{len(changed)} file(s) to upload...")
    ftp = connect_with_retries()

    try:
        for rel, path in changed.items():
            remote = rel  # upload relative to FTP root; no leading slash
            print(f"Uploading {rel} -> {remote}")
            try:
                upload_file(ftp, path, remote)
            except Exception as exc:
                print(f"  Skipped {rel}: {exc}")
    finally:
        ftp.quit()

    save_state(new_state)
    print("Sync complete.")


if __name__ == "__main__":
    main()
