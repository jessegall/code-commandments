# Whether an order carries a note, asked by defaulting the note to the blank and comparing it to that
# same blank — so "no note" and "an empty note" cannot be told apart. Below it, the FIX: the note is
# optional in its type and asked as `None`.


class Order:
    def __init__(self, note: str | None) -> None:
        self.note = note

    def has_note(self) -> bool:
        # @sin CancelledFallback
        return (self.note or "") != ""

    # @fixed CancelledFallback
    def carries_note(self) -> bool:
        return self.note is not None
