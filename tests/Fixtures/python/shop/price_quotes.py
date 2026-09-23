# A price quote handed back as a dict its callers read by string key — the quote's fields written
# nowhere as a type. Below it, the FIX: a frozen dataclass built where it is returned.
from dataclasses import dataclass


def price_quote(order, rates) -> dict:
    # @sin DictReturnBag
    return {"total": order.total(), "tax": rates.vat(order), "currency": "EUR"}


# @fixed DictReturnBag
@dataclass(frozen=True)
class PriceQuote:
    total: int
    tax: int
    currency: str


# @fixed DictReturnBag
def quote_for(order, rates) -> PriceQuote:
    return PriceQuote(total=order.total(), tax=rates.vat(order), currency="EUR")
