"""A small reader for the supplier's price list, one token at a time."""

from enum import StrEnum


class Punctuation(StrEnum):
    COMMA = ","
    QUOTE = '"'
    NEWLINE = "\n"


class PriceListReader:
    def __init__(self, text: str) -> None:
        self.text = text
        self.at = 0

    def expect(self, token: str) -> None:
        if not self.text.startswith(token, self.at):
            raise ValueError(f"expected {token!r} at {self.at}")
        self.at += len(token)

    def row_end(self) -> None:
        self.expect(Punctuation.NEWLINE)

    def field_end(self) -> None:
        # @sin UnnamedVocabularyLiteral
        self.expect(",")

    # @fixed UnnamedVocabularyLiteral
    def quoted_end(self) -> None:
        self.expect(Punctuation.QUOTE)
