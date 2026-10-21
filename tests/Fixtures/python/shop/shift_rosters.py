# The roster for one shift, built by walking the people who signed up and stamping each one's slot onto the
# sheet. The walk writes the sheet's own counts as it goes and answers None, and the builder underneath reads
# the counts off to decide whether the shift is covered.

COVER = 3


class RosterSheet:
    def __init__(self) -> None:
        self.slots: list[str] = []
        self.filled = 0


class ShiftRoster:
    def cover(self, signed_up: list[str]) -> bool:
        sheet = RosterSheet()
        self.stamp_slots(sheet, signed_up)

        return sheet.filled >= COVER

    # @sin FilledParameter
    # @sin FeatureEnvy
    def stamp_slots(self, sheet: RosterSheet, signed_up: list[str]) -> None:
        for at, person in enumerate(signed_up):
            sheet.slots.append(f"{at}:{person}")

        sheet.filled = len(signed_up)
