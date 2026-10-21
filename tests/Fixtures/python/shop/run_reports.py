# Two reports over the ledger, each raising for itself when a run is not there.
from shop.run_ledger import RunLedger, RunMissing


class RunReports:
    def __init__(self, ledger: RunLedger) -> None:
        self.ledger = ledger

    def miles(self, reference: str) -> int:
        run = self.ledger.referenced(reference)
        if run is None:
            raise RunMissing(reference)

        return run.miles

    def shown(self, reference: str) -> str:
        found = self.ledger.referenced(reference)
        if found is None:
            raise RunMissing(reference)

        return f"{found.reference}: {found.miles} miles"
