# Invoice numbering, with a comment that tells where the function came from. Below it, the FIX: the history
# deleted, and a comment only where a reason cannot be read off the code.


# formerly lived in the checkout module, before invoices had their own
# @sin ArchaeologyComment
def next_number(last: int, prefix: str) -> str:
    return f"{prefix}-{last + 1:06d}"


# @fixed ArchaeologyComment
def next_invoice_number(last: int, prefix: str) -> str:
    # six digits, because the accounting export pads to a fixed width
    return f"{prefix}-{last + 1:06d}"
