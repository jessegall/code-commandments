# Two reports over the ledger, each taking the run as there.
from shop.run_ledger import RunLedger


class RunReports:
    # @sin ConcreteCrossModuleDependency
    def __init__(self, ledger: RunLedger) -> None:
        self.ledger = ledger

    def miles(self, reference: str) -> int:
        return self.ledger.referenced(reference).miles

    def shown(self, reference: str) -> str:
        return f"{self.ledger.referenced(reference).reference} reported"
