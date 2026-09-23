"""Stock audits, each run against one shelf."""


class AuditedShelf:
    def __init__(self, code: str, aisle: int) -> None:
        self.code = code
        self.aisle = aisle


# @sin CoupledFields
class ShelfAudit:
    def __init__(self, shelf: AuditedShelf, shelf_code: str, counted: int) -> None:
        self.shelf = shelf
        self.shelf_code = shelf_code
        self.counted = counted


# @fixed CoupledFields
class HonestAudit:
    def __init__(self, shelf: AuditedShelf, counted: int) -> None:
        self.shelf = shelf
        self.counted = counted

    def shelf_code(self) -> str:
        return self.shelf.code


# @righteous CoupledFields
class AuditRow:
    def __init__(self, code: str, aisle: int, counted: int) -> None:
        self.code = code
        self.aisle = aisle
        self.counted = counted

    def cells(self) -> tuple[str, int, int]:
        return (self.code, self.aisle, self.counted)

    def csv(self) -> str:
        return ",".join(map(str, (self.code, self.aisle, self.counted)))
