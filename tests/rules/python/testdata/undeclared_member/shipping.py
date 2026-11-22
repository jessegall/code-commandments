class ShippingRates:
    def quote(self, zone: str) -> int:
        return len(zone) * 15


def lane_totals(rates: ShippingRates, zones: list[str]) -> list[int]:
    totals = []
    for zone in zones:
        # @sin UndeclaredMember
        totals.append(rates.surcharge + rates.quote(zone))
    return totals
