"""Tax bands by product category, loaded once at start-up."""

from dataclasses import dataclass, field


@dataclass
class TaxBand:
    percent: int = 0


@dataclass
class TaxTable:
    bands: dict[str, TaxBand] = field(default_factory=dict)

    # @sin DeNulledFinder
    def band_for(self, category: str) -> TaxBand | None:
        # @sin NullableRegistryLookup
        return self.bands.get(category)


def percent_for(table: TaxTable, category: str) -> int:
    """Every caller reads the band through as though a category always had one."""
    return table.band_for(category).percent


def is_zero_rated(table: TaxTable, category: str) -> bool:
    return table.band_for(category).percent == 0
