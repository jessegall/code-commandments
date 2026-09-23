# The command line reads the same settings file — through a copy of the reader, renamed, that the
# settings module does not know about.

import json
from pathlib import Path

from shop.settings import SettingsMissing


# @sin DuplicateFunction
def load_config(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}
