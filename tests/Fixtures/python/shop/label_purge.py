from collections.abc import Callable
from os import path


def purge_labels(purge: Callable[[str], None], expired: bool) -> None:
    if not expired:
        return

    # @sin RepeatedFolderPath
    purge(path.join("warehouse/saved-labels", "expired.pdf"))


# @righteous RepeatedFolderPath
def purge_draft(purge: Callable[[str], None]) -> None:
    purge(path.join("warehouse-drafts", "label.pdf"))
