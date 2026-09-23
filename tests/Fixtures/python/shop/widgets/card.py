# A widget whose import of a screen was moved into the function body to dodge the circular import. The arrow
# still points up the stack.


class Card:
    def __init__(self, title: str) -> None:
        self.title = title

    def __str__(self) -> str:
        return self.title

    def link(self) -> str:
        # @sin NamespaceDependency
        from shop.screens import home
        return home.path(self.title)
