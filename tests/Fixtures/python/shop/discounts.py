# Applying a discount rule that arrives as a dict: its fields are fetched with `.get` wherever a
# decision needs one.


def discounted(price: int, rule: dict) -> int:
    # @sin DictBag
    if rule.get("kind") == "percent":
        # @sin DictBag
        return price - price * rule.get("amount") // 100
    # @sin DictBag
    return price - rule.get("amount")
