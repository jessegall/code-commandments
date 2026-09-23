# A mail sent with a carbon copy only when one is given, the keyword spread in from a conditional
# dict. Below it, the FIX: the keyword always passed, the transport treating None as "no copy". And
# look-alikes: a plain spread, and a spread choosing between two real mappings.


def notify(transport, to: str, body: str, cc: str | None = None) -> None:
    # @sin ConditionalSpread
    transport.send(to, body, **({"cc": cc} if cc else {}))


# @fixed ConditionalSpread
def notify_all(transport, to: str, body: str, cc: str | None = None) -> None:
    transport.send(to, body, cc=cc)


# @righteous ConditionalSpread
def headers(base: dict, extra: dict) -> dict:
    return {**base, **extra}


# @righteous ConditionalSpread
def layout(wide: bool, full: dict, narrow: dict) -> dict:
    return {**(full if wide else narrow)}
