# Look-alikes: one conditional is a value chosen once, two side by side are two separate choices, and
# a conditional that only forms the TEST of another leaves both branches plain.


# @righteous NestedConditional
def label(order) -> str:
    return "paid" if order.paid else "open"


# @righteous NestedConditional
def window(page: int, last: int) -> tuple:
    return ("first" if page == 1 else "middle", "end" if page == last else "more")


# @righteous NestedConditional
def mode(reading: bool, binary: bool) -> str:
    return "r" if (binary if reading else False) else "w"
