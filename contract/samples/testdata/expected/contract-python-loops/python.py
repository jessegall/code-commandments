def scan(items):
    for item in items:
        if not item:
            # @contract kind/continue
            continue
        print(item)


async def drain(source):
    # @contract fact/loop/async = true
    async for chunk in source:
        print(chunk)
