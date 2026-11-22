def check(items):
    # @contract kind/unsupported
    # @contract fact/unsupported/construct = "assert"
    # @contract role/unsupported/parts -> as_items
    # @label as_items reference 12
    assert items
    return items[1:2]
