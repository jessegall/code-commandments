# A category tree walked with the current path kept on the walker, saved and restored around each
# child. Below it, the FIX: the path handed down as an argument.


class CategoryWalker:
    def __init__(self) -> None:
        self.path: list = []
        self.seen: list = []

    # @sin ScratchStateRestore
    def visit(self, category) -> None:
        previous = self.path
        self.path = [*previous, category.name]
        try:
            self.seen.append("/".join(self.path))
            for child in category.children:
                self.visit(child)
        finally:
            self.path = previous


# @fixed ScratchStateRestore
class CategoryPaths:
    def __init__(self) -> None:
        self.seen: list = []

    def visit(self, category, path: tuple = ()) -> None:
        here = (*path, category.name)
        self.seen.append("/".join(here))
        for child in category.children:
            self.visit(child, here)
