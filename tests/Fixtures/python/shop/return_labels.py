# A return label whose class docstring names the function that prints it, which was never written.


# @sin DanglingDocReference
class ReturnLabel:
    """A prepaid label, printed by :func:`~shop.return_labels.print_label`."""

    def __init__(self, order_ref: str) -> None:
        self.order_ref = order_ref
