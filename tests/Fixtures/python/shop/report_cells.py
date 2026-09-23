# A report renders cells that may hold a nested table of numbers; the renderer and the exporter each
# narrow through two levels to find one.


class Table:
    def __init__(self, rows: list) -> None:
        self.rows = rows


class Cell:
    def __init__(self, content: object) -> None:
        self.content = content


class ReportRenderer:
    def render(self, cell: object) -> str:
        # @sin RepeatedTypeGuard
        if isinstance(cell, Cell) and isinstance(cell.content, Table):
            return f"<table rows={len(cell.content.rows)}>"
        return str(cell)

    def export(self, cells: list) -> list:
        # @sin RepeatedTypeGuard
        return [cell.content.rows for cell in cells if isinstance(cell, Cell) and isinstance(cell.content, Table)]
