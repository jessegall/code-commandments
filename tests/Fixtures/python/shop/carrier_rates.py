# A carrier's rate card cached as text and decoded on the way out. Below it, the FIX: typed. And
# look-alikes: a round trip of our own value, and a return annotated with a TypedDict.
import json
from typing import TypedDict


class RateCache:
    def __init__(self, store) -> None:
        self.store = store

    def rates(self, carrier: str):
        cached = self.store.get(f"rates:{carrier}")
        # @sin RawDecodedReturn
        return json.loads(cached)


class RateCard(TypedDict):
    carrier: str
    per_kg: int


# @fixed RawDecodedReturn
class TypedRateCache:
    def __init__(self, store) -> None:
        self.store = store

    def rates(self, carrier: str) -> RateCard:
        return json.loads(self.store.get(f"rates:{carrier}"))


# @righteous RawDecodedReturn
def detached(settings: dict) -> dict:
    return json.loads(json.dumps(settings))
