"""Packs a day's product photos into a zip for the web team."""

import os
import time
import zipfile


# @sin DivergentTwin
def archive_photos(folder: str) -> str:
    target = f"{folder}-{time.strftime('%Y%m%d')}.zip"
    with zipfile.ZipFile(target, "w") as archive:
        for root, _, names in os.walk(folder):
            for name in names:
                archive.write(os.path.join(root, name), os.path.relpath(os.path.join(root, name), folder))
    os.chmod(target, 0o640)
    return target


# @sin DivergentTwin
def archive_thumbnails(folder: str) -> str:
    target = f"{folder}-{time.strftime('%Y%m%d')}.zip"
    with zipfile.ZipFile(target, "w") as archive:
        for root, _, names in os.walk(folder):
            for name in names:
                archive.write(os.path.join(root, name), os.path.relpath(os.path.join(root, name), folder))
    return target

