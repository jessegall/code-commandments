# A receipt printer whose indentation is bumped for the lines of a bundle and put back after. Below it,
# the FIX: the indentation is a parameter of the lines being printed.


class ReceiptPrinter:
    def __init__(self, out) -> None:
        self.out = out
        self.indent = 0

    def line(self, text: str) -> None:
        self.out.append(" " * self.indent + text)

    # @sin ScratchStateRestore
    def bundle(self, name: str, items: list) -> None:
        self.line(name)
        depth = self.indent
        self.indent = depth + 2
        for item in items:
            self.line(item)
        self.indent = depth


# @fixed ScratchStateRestore
class IndentedPrinter:
    def __init__(self, out) -> None:
        self.out = out

    def line(self, text: str, indent: int = 0) -> None:
        self.out.append(" " * indent + text)

    def bundle(self, name: str, items: list) -> None:
        self.line(name)
        for item in items:
            self.line(item, indent=2)
