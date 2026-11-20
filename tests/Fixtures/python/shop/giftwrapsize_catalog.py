from typing import Protocol


class GiftwrapSize(Protocol):
    label: str


class SampleGiftwrapSize(GiftwrapSize):
    label = "Sample"


class SmallGiftwrapSize(GiftwrapSize):
    label = "Small"


class RegularGiftwrapSize(GiftwrapSize):
    label = "Regular"


class LargeGiftwrapSize(GiftwrapSize):
    label = "Large"


class DisplayGiftwrapSize(GiftwrapSize):
    label = "Display"


def all_types() -> list[type[GiftwrapSize]]:
    # @sin ListMirrorsFolder
    return [SampleGiftwrapSize, SmallGiftwrapSize, RegularGiftwrapSize, LargeGiftwrapSize, DisplayGiftwrapSize]


# @righteous ListMirrorsFolder
def starter_types() -> list[type[GiftwrapSize]]:
    return [SampleGiftwrapSize, SmallGiftwrapSize]
