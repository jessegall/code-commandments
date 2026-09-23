# A label queue whose raise is narrated by the comment above it. Beside it, a comment holding code that was
# switched off, which is no narration.


class LabelQueue:
    def __init__(self) -> None:
        self.labels: list[str] = []

    def pop(self) -> str:
        if not self.labels:
            # raise an error, the label queue is empty
            # @sin RestatedComment
            raise IndexError("the label queue is empty")
        return self.labels.pop(0)

    # @righteous RestatedComment
    def peek(self) -> str:
        # return self.labels[-1]
        return self.labels[0]
