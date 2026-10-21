# The sheets posted for a shift. Both readings assert the bay is there rather than deciding anything about it
# being gone, so the roster is the one that should decide.
from shop.bay_roster import BayRoster


class BaySheets:
    def __init__(self, roster: BayRoster) -> None:
        self.roster = roster

    def posted(self, number: str) -> str:
        bay = self.roster.numbered(number)
        assert bay is not None

        return f"bay {bay.number}"

    def swept(self, number: str) -> str:
        found = self.roster.numbered(number)
        assert found is not None

        return f"{found.number} swept"
