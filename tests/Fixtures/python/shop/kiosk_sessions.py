# A kiosk session, which the till asks about while a customer is at the screen. The guard below answers "no
# minutes left" for a session the annotation says is always there, so the till can never tell a real zero from
# a session nobody passed.

IDLE = 0


class KioskSession:
    def __init__(self, token: str, minutes: int) -> None:
        self.token = token
        self.minutes = minutes


class KioskScreen:
    def minutes_left(self, session: KioskSession) -> int:
        # @sin ImpossibleGuard
        if session is None:
            return IDLE

        return session.minutes

    def is_idle(self, session: KioskSession) -> bool:
        return self.minutes_left(session) == IDLE
