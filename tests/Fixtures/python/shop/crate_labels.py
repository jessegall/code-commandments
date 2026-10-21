# The labels printed for a crate. Both readings take the lookup's answer as a crate and neither would know what to
# do with None, which is what makes the registry's `| None` a lie. Below them, the FIX reading the finder that
# answers a crate or raises.
from shop.crate_registry import CrateRegistry


class CrateLabels:
    def __init__(self, registry: CrateRegistry) -> None:
        self.registry = registry

    def label(self, reference: str) -> str:
        return self.registry.find(reference).reference

    def weight(self, reference: str) -> int:
        return self.registry.find(reference).weight

    # @fixed DeNulledFinder
    def labelled(self, reference: str) -> str:
        return self.registry.crate(reference).reference
