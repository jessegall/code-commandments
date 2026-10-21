# A hold placed on stock, wrapped in a clause that catches the failure and raises it straight back: the try
# promises the reader that something is handled here, and nothing is. Below it, the FIX: no clause at all,
# since the failure reaches the caller either way and now it is plain that it does.


def hold(ledger, sku: str, quantity: int) -> None:
    try:
        ledger.hold(sku, quantity)
    # @sin RethrownUnchanged
    except RuntimeError:
        raise


# @fixed RethrownUnchanged
def hold_stock(ledger, sku: str, quantity: int) -> None:
    ledger.hold(sku, quantity)
