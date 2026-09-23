# Exporting product rows from a loose payload: a missing SKU is written as an empty cell.


def export_row(writer, payload: dict) -> None:
    # @sin InventedDefault
    writer.writerow([str(payload.get("sku") or ""), payload["name"], payload["price"]])
