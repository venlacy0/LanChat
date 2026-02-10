#!/usr/bin/env python3
"""
生成 FTP 同步状态文件，为所有现有文件创建哈希。
运行此脚本后，ftp_sync.py 将只上传修改过的文件。
"""

import hashlib
import json
from pathlib import Path
from typing import Dict, Iterable

# 使用与 ftp_sync.py 相同的配置
ROOT = Path(__file__).resolve().parent.parent
STATE_FILE = ROOT / ".ftp_sync_state.json"

EXCLUDE_PATHS = {
    "data/messages.json",
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


def iter_files(base: Path) -> Iterable[Path]:
    """遍历所有需要同步的文件"""
    for path in base.rglob("*"):
        if path.is_dir():
            if path.name in EXCLUDE_DIRS:
                continue
        elif path.is_file():
            rel = path.relative_to(base).as_posix()
            if rel.startswith("."):  # 跳过根目录的隐藏文件
                continue
            if any(rel == ex or rel.startswith(ex.rstrip("*")) for ex in EXCLUDE_PATHS):
                continue
            if rel.startswith("data/rate_"):
                continue
            if any(part in EXCLUDE_DIRS for part in path.parts):
                continue
            yield path


def md5sum(path: Path) -> str:
    """计算文件的 MD5 哈希"""
    h = hashlib.md5()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


def main() -> None:
    print("正在扫描项目文件...")
    state: Dict[str, str] = {}
    file_count = 0

    for path in iter_files(ROOT):
        rel = path.relative_to(ROOT).as_posix()
        try:
            digest = md5sum(path)
            state[rel] = digest
            file_count += 1
            if file_count % 10 == 0:
                print(f"  已处理 {file_count} 个文件...")
        except Exception as e:
            print(f"  警告: 无法处理 {rel}: {e}")

    print(f"\n总共处理了 {file_count} 个文件")
    print(f"正在保存状态到 {STATE_FILE.name}...")

    STATE_FILE.write_text(json.dumps(state, indent=2, ensure_ascii=False))

    print("✅ 完成！状态文件已创建。")
    print(f"现在运行 ftp_sync.py 将只上传修改过的文件。")


if __name__ == "__main__":
    main()
