def label(count, parts, extra, items):
    total = count + 1
    sign = -count
    flag = not count
    size = "many" if count > 1 else "one"
    text = f"{size}: {count!r} items"
    joined = [*parts, 1]
    merged = {**extra, "k": 1}
    del items[0]
    return total, sign, flag, text, joined, merged


def relay(items):
    yield from items


async def fetch(source):
    return await source.read()
