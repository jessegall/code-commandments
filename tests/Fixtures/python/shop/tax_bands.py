"""Tax bands by product category, loaded once at start-up."""

from dataclasses import dataclass, field


@dataclass
class TaxBand:
    percent: int = 0


@dataclass
class TaxTable:
    bands: dict[str, TaxBand] = field(default_factory=dict)

    def band_for(self, category: str) -> TaxBand | None:
        # @sin NullableRegistryLookup
        return self.bands.get(category)
