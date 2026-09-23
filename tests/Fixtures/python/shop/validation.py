# Look-alikes: Python's specific builtins name a category callers catch by, and argument checks with
# them are the language's own convention.


def set_quantity(line, quantity: int) -> None:
    if quantity < 0:
        # @righteous MessageStringRaise
        raise ValueError(f"quantity must not be negative, got {quantity}")
    if not isinstance(quantity, int):
        # @righteous MessageStringRaise
        raise TypeError("quantity must be an int")
    line.quantity = quantity
