# A supplier feed read over the network, a timeout reported as the shop's own error without the cause.
# Below it, the FIX: chained. And look-alikes that already say what they mean — a bare re-raise, the
# cause cut on purpose with `from None`, and the cause set by hand.


class FeedUnavailable(Exception):
    @classmethod
    def at(cls, url: str) -> "FeedUnavailable":
        return cls(f"the feed at {url} did not answer")


def read_feed(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError as timeout:
        client.close()
        # @sin RaiseWithoutCause
        raise FeedUnavailable.at(url)


# @fixed RaiseWithoutCause
def read_feed_from(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError as timeout:
        client.close()
        raise FeedUnavailable.at(url) from timeout


# @righteous RaiseWithoutCause
def read_or_reraise(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError:
        client.close()
        raise


# @righteous RaiseWithoutCause
def setting(settings: dict, name: str) -> str:
    try:
        return settings[name]
    except KeyError:
        raise AttributeError(name) from None


# @righteous RaiseWithoutCause
def read_chained_by_hand(client, url: str) -> bytes:
    try:
        return client.get(url, timeout=5).content
    except TimeoutError as timeout:
        unavailable = FeedUnavailable.at(url)
        unavailable.__cause__ = timeout
        raise unavailable
