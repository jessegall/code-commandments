# Reading an order payload through small helpers: the dict is still read by string keys and a missing
# field still becomes "" — one call deeper, where neither shows at the call site.


def text_of(raw: dict, *keys) -> str:
    for key in keys:
        value = raw.get(key)
        if value:
            return str(value)
    # @sin InventedDefault
    return ""


def summary(payload: dict) -> str:
    # @sin DictBag
    return f"{text_of(payload, 'number')}: {text_of(payload, 'customer', 'email')}"


def note_for(order, notes) -> None:
    # @sin InventedDefault
    notes.append(order.gift_message if order.gift_message else "")
