# What the board says about an hour: one reading takes the slot as given, the other has something to say when
# there is none, which is why the board may answer None.
from shop.slot_board import SlotBoard


class SlotNotices:
    def __init__(self, board: SlotBoard) -> None:
        self.board = board

    def notice(self, hour: int) -> str:
        return f"slot at {self.board.at(hour).at}"

    def quiet(self, hour: int) -> str:
        slot = self.board.at(hour)
        if slot is None:
            return "nothing booked"

        return f"booked for {slot.at}"
