# Reading the basket from the current session: when there is none, the failure is described in prose
# at the raise, as a RuntimeError nobody can catch by name. Below it, the FIX: the failure is a class
# with a factory that writes its message once.


# @fixed MessageStringRaise
class NoActiveSession(LookupError):
    @classmethod
    def reading(cls, what: str) -> "NoActiveSession":
        return cls(f"there is no active session to read the {what} from")


def basket(context):
    if context.session is None:
        # @sin MessageStringRaise
        raise RuntimeError("There is no active session in this context.")
    return context.session.basket


# @fixed MessageStringRaise
def current_basket(context):
    if context.session is None:
        raise NoActiveSession.reading("basket")
    return context.session.basket
