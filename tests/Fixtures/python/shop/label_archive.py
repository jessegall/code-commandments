import os
from collections.abc import Callable

SAVED_LABELS = os.path.join("warehouse", "saved-labels")


def archive_label(save: Callable[[str, bytes], None], printed: bytes) -> None:
    # @sin RepeatedFolderPath
    save(os.path.join("warehouse", "saved-labels", "archive.pdf"), printed)


# @fixed RepeatedFolderPath
def archive_saved_label(save: Callable[[str, bytes], None], printed: bytes) -> None:
    save(os.path.join(SAVED_LABELS, "archive.pdf"), printed)
