# The shop's settings file, read once at start-up.

import json
from pathlib import Path


class SettingsMissing(Exception):
    pass


# @sin DuplicateFunction
def read_settings(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}
