# A basket's coupon checked by a longhand default compared to itself. Below it, the FIX: absence asked
# as `None`. And look-alikes: a default compared to a different value, and a `None` fallback, which is
# the absence itself.


def has_coupon(basket) -> bool:
    # @sin CancelledFallback
    return (basket.coupon if basket.coupon is not None else "") != ""


# @fixed CancelledFallback
def carries_coupon(basket) -> bool:
    return basket.coupon is not None


# @righteous CancelledFallback
def is_staff_coupon(basket) -> bool:
    return (basket.coupon or "") == "STAFF"


# @righteous CancelledFallback
def lacks_referrer(order) -> bool:
    return order.meta.get("referrer", None) is None
