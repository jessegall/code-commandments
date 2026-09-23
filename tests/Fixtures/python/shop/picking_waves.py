"""Warehouse picking, done in waves; each wave names its zone."""


class Zone:
    code: str = ""


class PickingWave:
    def __init__(self) -> None:
        self.zone: Zone | None = None
        self.picked: list[str] = []

    def start(self, zone: Zone) -> None:
        self.zone = zone

    def slip(self, sku: str) -> str:
        # @sin MaskedInvariant
        where = self.zone.code if self.zone is not None else "unknown"
        return f"{sku} @ {where}"


class Pallet:
    def __init__(self) -> None:
        self.zone: Zone | None = None

    def move(self, zone: Zone) -> None:
        self.zone = zone

    def described(self, fallback: str) -> str:
        # @righteous MaskedInvariant
        return self.zone.code if self.zone else fallback
