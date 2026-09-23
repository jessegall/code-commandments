"""A kiosk receipt, printed from the finished session's own answers."""


class KioskSession:
    total: int = 0

    def items(self) -> int:
        return 0

    def paid_by_card(self) -> bool:
        return False


def receipt(total: int, items: int, card: bool) -> str:
    return f"{items} items, {total} ({'card' if card else 'cash'})"


# @fixed DerivedArgument
def receipt_of(session: KioskSession) -> str:
    return f"{session.items()} items, {session.total} ({'card' if session.paid_by_card() else 'cash'})"


def finish(session: KioskSession) -> str:
    # @sin DerivedArgument
    return receipt(session.total, session.items(), session.paid_by_card())


def banner(title: str, subtitle: str) -> str:
    return f"{title} — {subtitle}"


def kiosk_banner(session: KioskSession) -> str:
    # @righteous DerivedArgument
    return banner(str(session.total), str(session.items()))
