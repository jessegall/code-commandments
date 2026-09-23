# An export job counting its runs in a class attribute declared between two methods. Below it, the
# FIX: the attribute with the rest of the state. And look-alikes: a property built from the method
# above it, and a protocol bound by assignment.


class ExportJob:
    def __init__(self, target) -> None:
        self.target = target

    # @sin MemberAfterMethod
    runs = 0

    def run(self, rows: list) -> None:
        self.target.write(rows)


# @fixed MemberAfterMethod
class ImportJob:
    runs = 0

    def __init__(self, source) -> None:
        self.source = source

    def run(self) -> list:
        return self.source.read()


class ExportSlot:
    def __init__(self, hour: int) -> None:
        self._hour = hour

    def _get_hour(self) -> int:
        return self._hour

    def __eq__(self, other) -> bool:
        return self._hour == other._hour

    # @righteous MemberAfterMethod
    hour = property(_get_hour)

    # @righteous MemberAfterMethod
    __hash__ = None
