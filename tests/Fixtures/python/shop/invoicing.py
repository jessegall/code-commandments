# Invoicing walks the orders and does its work only for the delivered ones — the whole iteration
# pushed a level deep behind the condition. Below it, the FIX: a `continue` guard at the loop's door.


def invoice_all(orders, ledger) -> int:
    sent = 0
    for order in orders:
        # @sin LoopWrappedInIf
        if order.delivered:
            invoice = ledger.draft(order.number, order.total())
            ledger.send(invoice)
            sent += 1
    return sent


# @fixed LoopWrappedInIf
def invoice_delivered(orders, ledger) -> int:
    sent = 0
    for order in orders:
        if not order.delivered:
            continue
        invoice = ledger.draft(order.number, order.total())
        ledger.send(invoice)
        sent += 1
    return sent
