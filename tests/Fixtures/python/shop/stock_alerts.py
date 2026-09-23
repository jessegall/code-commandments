# A stock alert's channel, the levels without one answered by a bare return. Below it, the FIX: raise
# on what was not handled. And look-alikes: a real default, and arms that already answer None.
from enum import IntEnum


class AlertLevel(IntEnum):
    INFO = 1
    WARNING = 2
    CRITICAL = 3


class UnhandledLevel(Exception):
    @classmethod
    def of(cls, level: AlertLevel) -> "UnhandledLevel":
        return cls(f"no channel for level {level}")


def channel(level: AlertLevel):
    # @sin MatchWildcardReturnsNone
    match level:
        case AlertLevel.CRITICAL:
            return "sms"
        case AlertLevel.WARNING:
            return "email"
        case _:
            return


# @fixed MatchWildcardReturnsNone
def channel_for(level: AlertLevel) -> str:
    match level:
        case AlertLevel.CRITICAL:
            return "sms"
        case AlertLevel.WARNING | AlertLevel.INFO:
            return "email"
        case _:
            raise UnhandledLevel.of(level)


# @righteous MatchWildcardReturnsNone
def colour(level: AlertLevel) -> str:
    match level:
        case AlertLevel.CRITICAL:
            return "red"
        case _:
            return "grey"


# @righteous MatchWildcardReturnsNone
def escalation(level: AlertLevel):
    match level:
        case AlertLevel.INFO:
            return None
        case AlertLevel.CRITICAL:
            return "oncall"
        case _:
            return None
