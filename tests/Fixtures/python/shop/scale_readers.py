"""Reads the weight off the counter scale."""


class CounterScale:
    def grams(self) -> int:
        return 0


class ScaleReader:
    # @sin PhantomNullable
    scale: CounterScale | None = None

    def weigh(self) -> int:
        return self.scale.grams()

    def weigh_twice(self) -> int:
        return self.scale.grams() + self.scale.grams()


class HonestScaleReader:
    # @fixed PhantomNullable
    def __init__(self, scale: CounterScale) -> None:
        self.scale = scale

    def weigh(self) -> int:
        return self.scale.grams()
