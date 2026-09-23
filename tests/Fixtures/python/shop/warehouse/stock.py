# Stock levels that reach into billing for the invoice class, while billing reaches back into the warehouse:
# two packages that cannot be understood apart.
# @sin NamespaceCycle
from ..billing.invoice import Invoice


def reserve(sku: str, invoice: Invoice) -> str:
    return f"{sku} for {invoice.number}"
