# Finding the coupon a customer may use: every condition wraps the next, and the answer sits five
# levels in, at the tip of an arrow. Below it, the FIX: what does not apply is skipped at the door of
# each pass, and the one question left is asked at the loop's own level.


def usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer == customer.id:
            if coupon.starts <= today <= coupon.ends:
                # @sin DeepNesting
                if coupon.uses_left > 0:
                    if not coupon.revoked:
                        return coupon
    return None


# @fixed DeepNesting
def first_usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer != customer.id or coupon.revoked:
            continue
        if coupon.starts <= today <= coupon.ends and coupon.uses_left > 0:
            return coupon
    return None
