# A voucher guard whose refusal never returns, named as a narration of failing.
from typing import NoReturn


class VoucherRefused(Exception):
    @classmethod
    def for_code(cls, code: str) -> "VoucherRefused":
        return cls(f"voucher {code} is refused")


class VoucherGuard:
    def __init__(self, blocked: frozenset[str]) -> None:
        self.blocked = blocked

    # @sin NarratedCommand
    def fails_on(self, code: str) -> NoReturn:
        raise VoucherRefused.for_code(code)
