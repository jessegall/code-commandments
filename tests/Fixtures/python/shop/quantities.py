# An import row skipped when its quantity is zero — or missing, since the missing one is defaulted to
# zero first. Below it, the FIX: the two cases asked apart, a missing quantity refused.


class MissingQuantity(Exception):
    @classmethod
    def in_row(cls, row: dict) -> "MissingQuantity":
        return cls(f"row {row.get('sku')} has no quantity")


def stock_rows(rows: list, stock) -> None:
    for row in rows:
        # @sin CancelledFallback
        if row.get("qty", 0) == 0:
            continue
        stock.add(row["sku"], row["qty"])


# @fixed CancelledFallback
def stock_counted(rows: list, stock) -> None:
    for row in rows:
        if "qty" not in row:
            raise MissingQuantity.in_row(row)
        if row["qty"] == 0:
            continue
        stock.add(row["sku"], row["qty"])
