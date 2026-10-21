# A picking ticket gets an id of its own and a reading of the clock, and both defaults are worked out once:
# every ticket the process makes carries the same id and the same minute. The pair below them is the look-alike
# that is not the sin: a value that never changes, and a count.

import uuid
from decimal import Decimal


# @sin FrozenDefault
def open_ticket(aisle: str, ticket: uuid.UUID = uuid.uuid4()) -> str:
    return f"{aisle}/{ticket}"


# @righteous FrozenDefault
def price_ticket(aisle: str, surcharge: Decimal = Decimal("0.00"), lines: int = 1) -> str:
    return f"{aisle}: {surcharge * lines}"
