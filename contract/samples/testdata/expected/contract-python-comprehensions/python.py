def build(names):
    squares = [n * n for n in range(5) if n % 2 == 0]
    sizes = {name: len(name) for name in names}
    return squares, sizes


async def collect(source):
    return [item async for item in source]
