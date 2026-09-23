# Logging switched on at import, off an `and` at the top of the module. Below it, the FIX: an `if`.
# And look-alikes that keep their operator: its value assigned, returned, or handed to a call.
import os

VERBOSE = os.environ.get("SHOP_VERBOSE") == "1"


def configure_logging() -> None:
    print("logging on")


# @sin ShortCircuitStatement
VERBOSE and configure_logging()

# @fixed ShortCircuitStatement
if VERBOSE:
    configure_logging()


# @righteous ShortCircuitStatement
def display_name(user) -> str:
    return user.nickname or user.email


# @righteous ShortCircuitStatement
def ready(order) -> bool:
    ok = order.paid and order.packed
    return ok
