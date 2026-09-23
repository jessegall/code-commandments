# A cache warmed off the right side of an `or` whose value nothing reads — an `if` wearing an
# operator. Below it, the FIX: the condition said as an `if`.


class Cache:
    def __init__(self, store) -> None:
        self.store = store

    def ensure(self, key: str) -> None:
        # @sin ShortCircuitStatement
        self.store.has(key) or self.store.warm(key)
        self.store.touch(key)

    # @fixed ShortCircuitStatement
    def ensure_warm(self, key: str) -> None:
        if not self.store.has(key):
            self.store.warm(key)
        self.store.touch(key)
