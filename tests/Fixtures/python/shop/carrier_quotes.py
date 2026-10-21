# A quote from one carrier for one parcel. The walk below asks whether each quote is None, which its own
# annotation rules out, and skips it when it is: a quote nobody passed and a quote of nothing read the same.


class CarrierQuote:
    def __init__(self, carrier: str, cents: int) -> None:
        self.carrier = carrier
        self.cents = cents


class CarrierQuotes:
    def cheapest(self, quotes: list[CarrierQuote]) -> str:
        best = ""
        lowest = 0
        for quote in quotes:
            if best and quote.cents >= lowest:
                continue

            best = quote.carrier
            lowest = quote.cents

        return best

    def spoken(self, quote: CarrierQuote) -> str:
        # @sin ImpossibleGuard
        if quote is None:
            return "no quote"

        return f"{quote.carrier} at {quote.cents}"
