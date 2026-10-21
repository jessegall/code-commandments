# The notes pinned to one board, kept in a list the board builds. The getter hands that very list back, so any
# caller can pin or unpin without the board knowing, and the board cannot keep its own order. Below it, the
# FIX: the caller gets a tuple it cannot change, and the board keeps the pinning to itself.


class NoteBoard:
    def __init__(self) -> None:
        self._notes: list[str] = []

    def pin(self, note: str) -> None:
        self._notes.append(note)

    # @sin HandedOutCollection
    def notes(self) -> list[str]:
        return self._notes


# @fixed HandedOutCollection
class PinnedBoard:
    def __init__(self) -> None:
        self._notes: list[str] = []

    def pin(self, note: str) -> None:
        self._notes.append(note)

    def notes(self) -> tuple[str, ...]:
        return tuple(self._notes)
