"""Parcel weights read off the scale's display, and the label that prints them."""

from dataclasses import dataclass


@dataclass(frozen=True)
class ScaleReading:
    display: str


def weight_label(kilos: float) -> str:
    return f"{kilos:.2f} kg"


def labels(readings: list[ScaleReading]) -> list[str]:
    # @sin ConvertedArgument
    return [weight_label(float(reading.display)) for reading in readings]


def heaviest_label(readings: list[ScaleReading]) -> str:
    # @sin ConvertedArgument
    return weight_label(kilos=float(max(readings, key=lambda reading: float(reading.display)).display))


# @fixed ConvertedArgument
def reading_label(reading: ScaleReading) -> str:
    return f"{float(reading.display):.2f} kg"

