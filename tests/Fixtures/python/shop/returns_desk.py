# A returned item's condition picks what the desk does with it — four rungs asking the same attribute.


def process(item, stock, bin_):
    # @sin SubjectLadder
    if item.condition == "new":
        stock.restock(item)
    elif item.condition == "opened":
        stock.discount(item, percent=10)
    elif item.condition == "damaged":
        bin_.write_off(item)
    elif item.condition == "wrong":
        stock.return_to_supplier(item)
    else:
        bin_.inspect(item)
