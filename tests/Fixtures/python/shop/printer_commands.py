# A label printer's command line, a flag added only when the job asks for it, spread from a
# conditional list. Below it, the FIX: the flags collected from what the job says, nothing spread.


class LabelJob:
    def __init__(self, copies: int, duplex: bool) -> None:
        self.copies = copies
        self.duplex = duplex

    def command(self, printer: str) -> list:
        # @sin ConditionalSpread
        return ["lp", "-d", printer, *(["-o", "sides=two-sided-long-edge"] if self.duplex else []), "-n", str(self.copies)]

    # @fixed ConditionalSpread
    def command_for(self, printer: str) -> list:
        return ["lp", "-d", printer, *self.flags(), "-n", str(self.copies)]

    # @fixed ConditionalSpread
    def flags(self) -> list:
        if not self.duplex:
            return []
        return ["-o", "sides=two-sided-long-edge"]
