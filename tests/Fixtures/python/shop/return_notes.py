# The notes a returns clerk writes while walking a shipment, collected into a default list: the list is made
# once, when the def is defined, so one shipment's notes are already in it when the next clerk omits it.
# Below it, the FIX: the caller's list, or a fresh one made inside the body.


class ReturnsDesk:
    # @sin MutableDefault
    def walk(self, lines: list[str], notes: list[str] = []) -> list[str]:
        for line in lines:
            if line.startswith("damaged"):
                notes.append(line)

        return notes

    # @fixed MutableDefault
    def walk_freshly(self, lines: list[str], notes: list[str] | None = None) -> list[str]:
        written = list(notes) if notes is not None else []
        for line in lines:
            if line.startswith("damaged"):
                written.append(line)

        return written

    # @righteous MutableDefault
    def walk_titled(self, lines: list[str], title: str = "returns", limit: int = 10) -> str:
        return f"{title}: {len(lines[:limit])}"
