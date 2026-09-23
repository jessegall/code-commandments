# Quoting shipping takes the same three strings the booking takes.


# @sin DataClump
def quote(postcode: str, street: str, city: str, weight_kg) -> int:
    return 495 if postcode.startswith("1") else 695
