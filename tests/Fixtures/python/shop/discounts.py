# Applying a discount rule that arrives as a dict: its fields are fetched with `.get` wherever a
# decision needs one.


# @sin DictBag
def discounted(price: int, rule: dict) -> int:
    if rule.get("kind") == "percent":
        return price - price * rule.get("amount") // 100
    return price - rule.get("amount")
