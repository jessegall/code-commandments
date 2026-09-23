# A sync and an async reader over the same feed: alike line for line, yet neither can call the other
# — an `async for` is not a `for` — so they are two bodies, not one written twice. And two streams
# each set their own state in `__init__`: structure every class declares for itself.


class FeedStream:
    # @righteous DuplicateFunction
    def __init__(self, source, started: float, label: str) -> None:
        self._source = source
        self._started = started
        self._label = label
        self._closed = False

    # @righteous DuplicateFunction
    def chunks(self):
        with self._source.opened():
            for chunk in self._source:
                yield chunk.decode("utf-8").strip()


class AsyncFeedStream:
    # @righteous DuplicateFunction
    def __init__(self, source, started: float, label: str) -> None:
        self._source = source
        self._started = started
        self._label = label
        self._closed = False

    # @righteous DuplicateFunction
    async def chunks(self):
        async with self._source.opened():
            async for chunk in self._source:
                yield chunk.decode("utf-8").strip()
