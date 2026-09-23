# A feature-flag table whose comment points at a flag that is deliberately absent. Beside it, a comment about
# runtime state, which is no defence of the code.


class FeatureFlags:
    def __init__(self) -> None:
        self.enabled: set[str] = set()

    def enable(self, flag: str) -> None:
        # the beta checkout flag is intentionally not listed here
        # @sin NegativeSpaceComment
        self.enabled.add(flag)

    # @righteous NegativeSpaceComment
    def is_on(self, flag: str) -> bool:
        # a flag that is not enabled reads as off
        return flag in self.enabled
