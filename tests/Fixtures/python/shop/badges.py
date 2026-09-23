# Look-alikes that are NOT near-copies. Two lookup tables share a skeleton by construction — they call
# nothing and hand back constants, so they are data, not procedure. Two stubs leave the body to their
# subclasses. And two classes each set their own state in `__init__`.


# @righteous NearDuplicateFunction
def status_colour(status: str) -> str | None:
    if status == "paid":
        return "green"
    if status == "late":
        return "red"
    if status == "void":
        return "grey"
    return None


# @righteous NearDuplicateFunction
def status_icon(status: str) -> str | None:
    if status == "paid":
        return "check"
    if status == "late":
        return "clock"
    if status == "void":
        return "cross"
    return None


class Badge:
    # @righteous NearDuplicateFunction
    def __init__(self, label: str, colour: str, icon: str) -> None:
        self.label = label
        self.colour = colour
        self.icon = icon
        self.kind = "badge"
        self.visible = True

    # @righteous NearDuplicateFunction
    def render(self, order, context, options) -> str:
        """Render the badge: each kind decides how."""
        raise NotImplementedError(f"{type(self).__name__} renders {order.number} in {context.name} as {options.mode} for {self.label}")


class Ribbon:
    # @righteous NearDuplicateFunction
    def __init__(self, label: str, colour: str, icon: str) -> None:
        self.label = label
        self.colour = colour
        self.icon = icon
        self.kind = "ribbon"
        self.visible = True

    # @righteous NearDuplicateFunction
    def render(self, order, context, options) -> str:
        """Render the ribbon: each kind decides how."""
        raise NotImplementedError(f"{type(self).__name__} draws {order.number} in {context.name} as {options.mode} for {self.label}")
