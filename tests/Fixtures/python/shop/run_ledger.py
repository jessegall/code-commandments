# The runs a depot has sent out, with the miles each carried. Every caller raises its own error when the run is
# missing, each writing the same sentence about absence that the ledger could have written once.
class RunMissing(Exception):
    pass


class Run:
    def __init__(self, reference: str, miles: int) -> None:
        self.reference = reference
        self.miles = miles


class RunLedger:
    """Every run sent out, newest last, and the miles they add up to."""

    def __init__(self) -> None:
        self.runs: list[Run] = []

    def note(self, run: Run) -> None:
        self.runs.append(run)

    def miles(self) -> int:
        return sum(run.miles for run in self.runs)

    def longest(self) -> int:
        return max((run.miles for run in self.runs), default=0)

    # @sin DeNulledFinder
    def referenced(self, reference: str) -> Run | None:
        return next((run for run in self.runs if run.reference == reference), None)
