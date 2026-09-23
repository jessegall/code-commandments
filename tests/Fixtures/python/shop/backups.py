# A backup that copies a folder or a file, chosen in one line whose value is thrown away. Below it,
# the FIX: the choice said as an `if`.
import shutil
from pathlib import Path


def back_up(source: Path, target: Path) -> None:
    # @sin ConditionalStatement
    shutil.copytree(source, target) if source.is_dir() else shutil.copy2(source, target)


# @fixed ConditionalStatement
def back_up_path(source: Path, target: Path) -> None:
    if source.is_dir():
        shutil.copytree(source, target)
        return
    shutil.copy2(source, target)
