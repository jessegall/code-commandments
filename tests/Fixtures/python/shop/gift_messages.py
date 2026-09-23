# A gift message printer whose class docstring narrates its own refactor.


# @sin ArchaeologyComment
class GiftMessage:
    """The card text printed with a gift; refactored out of the packing slip renderer."""

    def __init__(self, sender: str, text: str) -> None:
        self.sender = sender
        self.text = text

    def card(self) -> str:
        return f"{self.text}\n  from {self.sender}"
