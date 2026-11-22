# @contract kind/import
# @contract role/import/alias -> alias
import decimal as money # @label alias binding


def zero() -> money.Decimal:
    return money.Decimal(0)
