# A basket's coupon checked by a longhand default compared to itself. Below it, the FIX: absence asked
# as `None`. And look-alikes: a default compared to a different value, and a `None` fallback, which is
# the absence itself.


def has_coupon(basket) -> bool:
    # @sin CancelledCoalesce
    return (basket.coupon if basket.coupon is not None else "") != ""


# @fixed CancelledCoalesce
def carries_coupon(basket) -> bool:
    return basket.coupon is not None


# @righteous CancelledCoalesce
def is_staff_coupon(basket) -> bool:
    return (basket.coupon or "") == "STAFF"


# @righteous CancelledCoalesce
def lacks_referrer(order) -> bool:
    return order.meta.get("referrer", None) is None
