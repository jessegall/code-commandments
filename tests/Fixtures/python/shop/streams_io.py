# A stream that was closed is read again — and the failure is a RuntimeError worded at the raise.


class LineStream:
    def __init__(self, source) -> None:
        self.source = source
        self.closed = False

    def read_line(self) -> str:
        if self.closed:
            # @sin MessageStringRaise
            raise RuntimeError("Attempted to read from a closed line stream.")
        return self.source.readline()
