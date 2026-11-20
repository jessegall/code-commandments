def restock(
    quantity: int,
    # @sin UnusedParameter
    threshold: int,
):
    boxed = []
    for crate in range(quantity):
        if crate % 2 == 0:
            boxed.append(crate)
    return len(boxed)


def restock_label(value: str, prefix: str) -> str:
    return prefix + value
