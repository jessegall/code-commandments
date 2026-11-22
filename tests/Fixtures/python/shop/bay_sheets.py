# The sheets posted for a shift. Both readings take the bay as there rather than deciding anything about it
# being gone, so the roster is the one that should decide.
from shop.bay_roster import BayRoster


class BaySheets:
    # @sin ConcreteCrossModuleDependency
    def __init__(self, roster: BayRoster) -> None:
        self.roster = roster

    def posted(self, number: str) -> str:
        return f"bay {self.roster.numbered(number).number}"

    def swept(self, number: str) -> str:
        return f"{self.roster.numbered(number).number} swept"
