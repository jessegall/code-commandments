# Look-alikes that keep their `else`. A loop's `else:` runs only when no `break` ended it — a
# different construct, with nothing to drop. An `if`/`elif`/`else` chain is a ladder, not a guard.
# And an `if` whose branch falls through is a two-armed decision.


# @righteous RedundantElse
def first_in_stock(skus, levels):
    for sku in skus:
        if levels.get(sku, 0) > 0:
            break
    else:
        return None
    return sku


# @righteous RedundantElse
def label(count: int) -> str:
    if count == 0:
        return "none"
    elif count == 1:
        return "one"
    else:
        return "many"


# @righteous RedundantElse
def note(order, log) -> None:
    if order.gift:
        log.write(f"{order.number}: wrap it")
    else:
        log.write(f"{order.number}: plain box")
