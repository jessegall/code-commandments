import os
import os.path


def entries(folder: str) -> int:
    # @sin FolderWalkOutsideTheFunnelDetector
    return len(os.listdir(folder))


def child(folder: str, name: str) -> str:
    # @righteous FolderWalkOutsideTheFunnelDetector
    return os.path.join(folder, name)
