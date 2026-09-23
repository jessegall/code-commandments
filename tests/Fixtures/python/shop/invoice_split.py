# An invoice total split into net, VAT and currency, handed back as a bare tuple the caller unpacks by
# position. Below it, the FIX: a named result.
from dataclasses import dataclass


def split_total(order, rates):
    # @sin PositionalTupleReturn
    return order.net(), rates.vat(order), order.currency


# @fixed PositionalTupleReturn
@dataclass(frozen=True)
class InvoiceSplit:
    net: int
    vat: int
    currency: str


# @fixed PositionalTupleReturn
def split_invoice(order, rates) -> InvoiceSplit:
    return InvoiceSplit(net=order.net(), vat=rates.vat(order), currency=order.currency)
