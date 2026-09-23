# Python flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### deep-python-nesting

An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops

```py
----------[ Bad ]----------

def usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer == customer.id:
            if coupon.starts <= today <= coupon.ends:
                if coupon.uses_left > 0:
                    if not coupon.revoked:
                        return coupon
    return None

----------[ Good ]----------

def first_usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer != customer.id or coupon.revoked:
            continue
        if coupon.starts <= today <= coupon.ends and coupon.uses_left > 0:
            return coupon
    return None
```

### redundant-python-else

An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing

```py
----------[ Bad ]----------

def price(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity
    else:
        subtotal = line.unit_price * line.quantity
        return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal

----------[ Good ]----------

def price_of(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity

    subtotal = line.unit_price * line.quantity
    return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal
```
