# Building the pick list skips the lines already picked — then puts the real work in an `else:`
# after the `continue`.


def pick_list(orders) -> list:
    picks = []
    for order in orders:
        for line in order.lines:
            # @sin RedundantElse
            if line.picked:
                continue
            else:
                picks.append((order.number, line.sku, line.quantity))
    return picks
