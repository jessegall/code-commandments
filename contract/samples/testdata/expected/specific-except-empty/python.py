def find(prices: dict[str, int]) -> None:
    try:
        prices["a"]
    except KeyError:
        pass
