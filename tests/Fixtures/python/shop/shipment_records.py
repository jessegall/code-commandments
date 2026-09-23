# A shipment marked delivered by rebuilding it field by field — every field re-listed to change one.
# Below it, the FIX: `dataclasses.replace`, naming only what changes.
from dataclasses import dataclass, replace


@dataclass(frozen=True)
class Shipment:
    number: str
    carrier: str
    weight: float
    status: str

    def is_heavy(self) -> bool:
        return self.weight > 20.0

    def tracking_url(self) -> str:
        return f"https://track.example/{self.carrier}/{self.number}"

    def delivered(self) -> "Shipment":
        # @sin HandRolledReplace
        return Shipment(self.number, self.carrier, self.weight, "delivered")

    # @fixed HandRolledReplace
    def arrived(self) -> "Shipment":
        return replace(self, status="arrived")
