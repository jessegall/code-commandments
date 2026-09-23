# Checkout: reserve every line of an order, and report what it came to.

from shop.inventory import Inventory
from shop.orders import Order


def checkout(order: Order, inventory: Inventory) -> int:
    if order.is_empty():
        return 0

    for line in order.lines:
        inventory.reserve(line.sku, line.quantity)

    return order.total()
