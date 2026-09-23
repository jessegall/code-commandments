# A refund policy whose docstring writes an essay before its argument section. Beside it, a class with one
# paragraph and a Sphinx field list, which is markup, not prose.


class RefundPolicy:
    # @sin BloatedDocblock
    class Rule:
        """One condition a refund must meet.

        Rules run in the order they were added, and the first that refuses ends the check, so put the
        cheap ones first.

        Args:
            name: what the refund desk calls it.
        """

        name = ""

    # @righteous BloatedDocblock
    class Window:
        """How many days after delivery a refund is accepted.

        :param days: the length of the window.
        """

        days = 30
