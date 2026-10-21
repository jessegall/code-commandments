# The desk that packs an order: it writes the slip and tells the printer. The courier it also asks for is kept
# under a name of its own and read nowhere, so the caller builds one for nothing. Below it, the FIX: a desk
# whose constructor asks for the two things it uses.


class Printer:
    def print_slip(self, text: str) -> None:
        pass


class Courier:
    def book(self, order: str) -> None:
        pass


class PackDesk:
    def __init__(self, printer: Printer, courier: Courier) -> None:
        self._printer = printer
        # @sin UnreadCollaborator
        self._courier = courier

    def pack(self, order: str, lines: list[str]) -> int:
        self._printer.print_slip(f"{order}: {len(lines)} lines")

        return len(lines)


# @fixed UnreadCollaborator
class PackingDesk:
    def __init__(self, printer: Printer) -> None:
        self._printer = printer

    def pack(self, order: str, lines: list[str]) -> int:
        for line in lines:
            self._printer.print_slip(f"{order}: {line}")

        return len(lines)
