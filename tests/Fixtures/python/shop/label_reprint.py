from collections.abc import Callable
from os.path import join


def reprint_labels(copies: int, send: Callable[[str], None]) -> None:
    # @sin RepeatedFolderPath
    file = join("warehouse", "saved-labels", "reprint.pdf")
    for _ in range(copies):
        send(file)
