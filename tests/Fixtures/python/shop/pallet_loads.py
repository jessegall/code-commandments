"""Pallets on the loading dock and the crates stacked on them."""

from dataclasses import dataclass, field


@dataclass
class Crate:
    kilos: int = 0


@dataclass
class Pallet:
    crates: list[Crate] = field(default_factory=list)

    # @fixed FeatureEnvy
    def heaviest(self) -> int:
        return max((crate.kilos for crate in self.crates), default=0)


class DockPlanner:
    # @sin FeatureEnvy
    def heaviest_crate(self, pallet: Pallet) -> int:
        heaviest = 0
        for crate in pallet.crates:
            heaviest = max(heaviest, crate.kilos)
        return heaviest

    # @fixed FeatureEnvy
    def heaviest_on(self, pallet: Pallet) -> int:
        return pallet.heaviest()
