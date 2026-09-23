# An invoice rendered one of two ways, chosen by a bool the caller passes. Below it, the FIX: the two
# renderings named for what they are, sharing the part they have in common.


class InvoiceView:
    def __init__(self, number: str, lines: list[str]) -> None:
        self.number = number
        self.lines = lines

    # @sin FlagArgument
    def render(self, compact: bool) -> str:
        # @sin RedundantElse
        if compact:
            return f"{self.number}: {len(self.lines)} lines"
        else:
            return "\n".join([self.number, *self.lines])


# @fixed FlagArgument
class InvoiceSheet:
    def __init__(self, number: str, lines: list[str]) -> None:
        self.number = number
        self.lines = lines

    def render_compact(self) -> str:
        return f"{self.number}: {len(self.lines)} lines"

    def render_full(self) -> str:
        return "\n".join([self.number, *self.lines])
