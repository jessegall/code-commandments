# Look-alikes: a dict whose keys are data (a SKU looked up by a value) is a mapping, not a record.


# @righteous DictBag
def level(stock: dict[str, int], sku: str) -> int:
    return stock.get(sku, 0) + stock[sku.upper()] if sku.upper() in stock else stock.get(sku, 0)
