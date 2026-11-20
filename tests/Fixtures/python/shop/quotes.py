# Quoting shipping takes the same three strings the booking takes.


# @sin DataClump
def quote(postcode: str, street: str, city: str, weight_kg) -> int:
    central = postcode.startswith("1") and street.strip() and city == "Amsterdam"
    return (495 if central else 695) + int(weight_kg * 20)
