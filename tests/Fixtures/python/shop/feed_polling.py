# Polls the supplier's feed until it answers, inside a coroutine. The sleep stops the event loop's own thread,
# so every other coroutine waiting on it stops too: the shop's whole async side pauses half a second at a time
# while this one waits. Below it, the FIX: the asynchronous sleep, awaited.

import asyncio
import time

EVERY = 0.5

TRIES = 3


class FeedPoller:
    def __init__(self, feed) -> None:
        self.feed = feed

    async def poll(self, supplier: str) -> str:
        for _ in range(TRIES):
            answered = await self.feed.read(supplier)
            if answered:
                return answered

            # @sin BlockedAwait
            time.sleep(EVERY)

        return ""


# @fixed BlockedAwait
class AwaitingPoller:
    def __init__(self, feed) -> None:
        self.feed = feed

    async def poll(self, supplier: str) -> str:
        for _ in range(TRIES):
            answered = await self.feed.read(supplier)
            if answered:
                return answered

            await asyncio.sleep(EVERY)

        return ""
