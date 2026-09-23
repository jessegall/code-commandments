# Which invoices get a reminder, decided by listing two members of the status enum at the call site.
# Below it, the FIX: the group named on the enum, asked as one question.
from enum import StrEnum


class InvoiceStatus(StrEnum):
    OPEN = "open"
    OVERDUE = "overdue"
    PAID = "paid"

    @property
    def is_unpaid(self) -> bool:
        return self in (InvoiceStatus.OPEN, InvoiceStatus.OVERDUE)


def needs_reminder(invoice) -> bool:
    # @sin EnumCaseOrChain
    return invoice.status == InvoiceStatus.OPEN or invoice.status == InvoiceStatus.OVERDUE


# @fixed EnumCaseOrChain
def owes_money(invoice) -> bool:
    return invoice.status.is_unpaid
