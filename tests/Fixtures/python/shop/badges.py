# Look-alikes that are NOT near-copies. Two lookup tables share a skeleton by construction — they call
# nothing and hand back constants, so they are data, not procedure. Two stubs leave the body to their
# subclasses. Two classes each set their own state in `__init__`. And two commands declare their arguments, one
# call per argument with nothing computed: a table written as calls.


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


class RefundCommand:
    # @righteous NearDuplicateFunction
    def arguments(self, parser) -> None:
        parser.add_argument("order_number", type=int)
        parser.add_argument("--reason", required=True, choices=["damaged", "late", "lost"])
        parser.add_argument("--note", default="")


class ShipCommand:
    # @righteous NearDuplicateFunction
    def arguments(self, parser) -> None:
        parser.add_argument("parcel_id", type=str)
        parser.add_argument("--carrier", required=True, choices=["post", "courier", "freight"])
        parser.add_argument("--label", default="standard")
