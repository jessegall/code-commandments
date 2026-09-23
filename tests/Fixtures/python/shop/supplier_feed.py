# A supplier's price feed fetched and handed on as whatever the JSON decoded to. Below it, the FIX: the
# feed parsed into typed prices where it arrives.
import json
from dataclasses import dataclass


def fetch_prices(client, supplier: str):
    # @sin RawDecodedReturn
    return json.loads(client.get(f"/suppliers/{supplier}/prices"))


# @fixed RawDecodedReturn
@dataclass(frozen=True)
class SupplierPrice:
    sku: str
    cents: int

    @classmethod
    def from_json(cls, raw: dict) -> "SupplierPrice":
        return cls(sku=raw["sku"], cents=int(raw["cents"]))


# @fixed RawDecodedReturn
def fetch_supplier_prices(client, supplier: str) -> list:
    return [SupplierPrice.from_json(raw) for raw in json.loads(client.get(f"/suppliers/{supplier}/prices"))]
