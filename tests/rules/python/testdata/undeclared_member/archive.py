class Archive:
    name: str

    def __init__(self, name: str) -> None:
        self.name = name


class ImportedArchive(Archive):
    def __getattr__(self, name: str) -> str:
        return self.name + "." + name


def manifest(archive: Archive) -> tuple[str, str]:
    # @sin UndeclaredMember
    return (archive.name, archive.sync_id)


def imported_reference(archive: ImportedArchive) -> str:
    # @righteous UndeclaredMember
    return archive.sync_id
