# The store being served kept in a module global that a request handler switches — whichever request
# ran last decides for every other. Below it, the FIX: the store held by the request it belongs to.

CURRENT_STORE = None


def enter_store(code: str) -> None:
    global CURRENT_STORE
    # @sin MutableStaticState
    CURRENT_STORE = code


# @fixed MutableStaticState
class RequestContext:
    def __init__(self, store: str) -> None:
        self.store = store
