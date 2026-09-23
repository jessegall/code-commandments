# An order archive whose loop comment says only what the loop says.


def archive(orders: list, shelf: list) -> None:
    # loop over each order
    # @sin RestatedComment
    for order in orders:
        shelf.append(order)
