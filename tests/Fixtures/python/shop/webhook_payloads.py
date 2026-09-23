# A webhook payload that carries a note only when there is one, spread in through a conditional into
# an empty dict. Below it, the FIX: a factory that drops what is absent, the note passed by name.
from dataclasses import dataclass


def payload(order_id: int, note: str | None) -> dict:
    # @sin ConditionalSpread
    return {"id": order_id, **({"note": note} if note is not None else {})}


# @fixed ConditionalSpread
@dataclass(frozen=True)
class Payload:
    fields: dict

    @classmethod
    def of(cls, **values) -> "Payload":
        return cls({key: value for key, value in values.items() if value is not None})


# @fixed ConditionalSpread
def payload_for(order_id: int, note: str | None) -> dict:
    return Payload.of(id=order_id, note=note).fields
