"""Shelf labels come in a few printed sizes."""

from enum import StrEnum


class LabelSize(StrEnum):
    SMALL = "small"
    WIDE = "wide"


def print_label(text: str, size: str) -> str:
    return f"[{size}] {text}"


def price_label(cents: int) -> str:
    return print_label(f"{cents / 100:.2f}", size=LabelSize.SMALL)


def promo_label(text: str) -> str:
    # @sin UnnamedVocabularyLiteral
    return print_label(text, size="wide")


def custom_label(text: str) -> str:
    # @righteous UnnamedVocabularyLiteral
    return print_label(text, size="poster")
