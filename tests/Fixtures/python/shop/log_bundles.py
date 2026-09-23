"""Bundles the tills' logs for support — the day's, or the whole week's."""

import datetime
import os
import tarfile


def bundle_week(folder: str) -> str:
    target = f"{folder}-week-{datetime.date.today().isoformat()}.tar.gz"
    with tarfile.open(target, "w:gz") as bundle:
        for entry in os.scandir(folder):
            bundle.add(entry.path, arcname=entry.name)
    os.utime(target, (os.path.getmtime(folder), os.path.getmtime(folder)))
    return target


def bundle_day(folder: str) -> str:
    target = f"{folder}-day-{datetime.date.today().isoformat()}.tar.gz"
    with tarfile.open(target, "w:gz") as bundle:
        for entry in os.scandir(folder):
            bundle.add(entry.path, arcname=entry.name)
    return target


# @righteous DivergentTwin
def bundle(folder: str, days: int) -> str:
    return bundle_week(folder) if days > 1 else bundle_day(folder)
