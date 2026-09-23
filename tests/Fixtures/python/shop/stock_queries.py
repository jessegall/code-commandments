# A stock query that answers two questions: leave the warehouse out and it counts every warehouse. The
# other function returns the carrier it was given or builds the default one, which is a value, not a
# second behaviour.


class Carrier:
    def __init__(self, name: str) -> None:
        self.name = name


# @sin FlagArgument
def units_in_stock(levels: dict[str, dict[str, int]], sku: str, warehouse: str | None = None) -> int:
    if warehouse is None:
        return sum(stock.get(sku, 0) for stock in levels.values())
    return levels[warehouse].get(sku, 0)


# @righteous FlagArgument
def carrier_or_default(given: Carrier | None = None) -> Carrier:
    if given is not None:
        return given
    return Carrier("post")
