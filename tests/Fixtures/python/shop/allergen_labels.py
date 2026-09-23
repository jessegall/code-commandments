"""Allergen warnings for the deli counter, kept per recipe code."""

from dataclasses import dataclass, field


@dataclass(frozen=True)
class Warnings:
    allergens: tuple[str, ...] = ()


@dataclass(frozen=True)
class DeliItem:
    recipe: str
    warnings: Warnings = field(default_factory=Warnings)

    # @fixed KeyedLookupEnvy
    def allergens(self) -> list[str]:
        return list(self.warnings.allergens)


class DeliCounter:
    def __init__(self, warnings: dict[str, Warnings]) -> None:
        self.warnings = warnings

    # @sin KeyedLookupEnvy
    def allergens_of(self, item: DeliItem) -> list[str]:
        return list(self.warnings[item.recipe].allergens)

    def warning_for(self, recipe: str) -> Warnings:
        return self.warnings[recipe]

    # @righteous KeyedLookupEnvy
    def is_nut_free(self, item: DeliItem) -> bool:
        return "nuts" not in self.warning_for(item.recipe).allergens
