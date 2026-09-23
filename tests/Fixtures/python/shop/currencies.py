# Look-alikes: a real default is a choice ("EUR" when none is given), and an empty collection is the
# type's own "no items" — neither invents data.


# @righteous InventedDefault
def price_label(amount: int, currency: str | None) -> str:
    return format_money(amount, currency or "EUR")


# @righteous InventedDefault
def render_lines(order, template) -> str:
    return template.render(order.lines or [])


def format_money(amount: int, currency: str) -> str:
    return f"{amount / 100:.2f} {currency}"
